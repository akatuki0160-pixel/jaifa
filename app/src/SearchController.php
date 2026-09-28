<?php

declare(strict_types=1);

namespace Qryno;

/** 会員番号のお問い合わせ画面（qryNo.php / qryNoExe.php） */
final class SearchController
{
    public function __construct(private readonly App $app)
    {
    }

    /** 入力画面 */
    public function showForm(): void
    {
        Http::startSession($this->app->sessionName());
        $this->renderForm(SearchForm::empty());
    }

    /** 検索の実行と結果表示 */
    public function execute(): void
    {
        if (!Http::isPost()) {
            Http::redirect('qryNo.php');
        }
        Http::startSession($this->app->sessionName());

        $input = [];
        foreach (SearchForm::FIELDS as $field) {
            $input[$field] = Http::post($field);
        }

        if (!Http::checkCsrf(Http::post('sesstxt'))) {
            $this->renderForm(
                SearchForm::fromInput($input),
                'ページの有効期限が切れたため検索できませんでした。お手数ですが、もう一度「検索」ボタンをクリックしてください。'
            );

            return;
        }

        $form = SearchForm::validate($input, $this->app->masters(), $this->maxNameLength());
        if (!$form->isValid()) {
            $this->renderForm($form);

            return;
        }

        $directory = $this->app->directory();
        if (!$directory->isReady()) {
            $this->renderResult('unavailable', $form, null, 503);

            return;
        }

        $limiter = $this->app->rateLimiter();
        $max = (int) $this->app->config->get('rate_limit.search.max', 30);
        $window = (int) $this->app->config->get('rate_limit.search.window', 600);
        $ip = Http::clientIp();
        if ($limiter->tooManyAttempts('search', $ip, $max, $window)) {
            $this->renderResult('limited', $form, null, 429);

            return;
        }

        $result = $directory->find($form->keys['kyokai'], $form->keys['company'], $form->keys['sei'], $form->keys['mei']);
        if (!$result->isFound() && $max > 0) {
            $limiter->hit('search', $ip, $window);
        }
        $this->renderResult($result->status, $form, $result->memberNo);
    }

    private function renderForm(SearchForm $form, string $message = ''): void
    {
        Http::sendHtmlHeaders();
        echo $this->app->view()->page('search/form', [
            'form' => $form,
            'message' => $message,
            'token' => Http::csrfToken(),
            'kyokaiOptions' => $this->app->masters()->kyokaiOptions(),
            'companyOptions' => $this->app->masters()->companyOptions(),
            'maxNameLength' => $this->maxNameLength(),
        ]);
    }

    /** @param string $status SearchResult の状態、または limited（回数制限）/ unavailable（データ未登録） */
    private function renderResult(string $status, SearchForm $form, ?string $memberNo, int $httpStatus = 200): void
    {
        Http::sendHtmlHeaders($httpStatus);
        $template = $status === SearchResult::FOUND ? 'search/found' : 'search/not_found';
        echo $this->app->view()->page($template, [
            'status' => $status,
            'form' => $form,
            'memberNo' => $memberNo,
        ]);
    }

    private function maxNameLength(): int
    {
        return (int) $this->app->config->get('search.max_name_length', 50);
    }
}
