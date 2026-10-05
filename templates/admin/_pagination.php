<?php
/**
 * @var array{page: int, pages: int, total: int} $pagination
 * @var \App\Http\Admin\ListFilters $filters
 * @var string $action
 */
$link = static function (int $page) use ($filters, $action): string {
    return url($action . '?' . http_build_query($filters->queryParams() + ['pagina' => (string) $page]));
};
?>
<p class="pagination">
    <?= e((string) $pagination['total']) ?> risultati · pagina <?= e((string) $pagination['page']) ?> di <?= e((string) $pagination['pages']) ?>
<?php if ($pagination['page'] > 1): ?>
    · <a href="<?= e($link($pagination['page'] - 1)) ?>">precedente</a>
<?php endif; ?>
<?php if ($pagination['page'] < $pagination['pages']): ?>
    · <a href="<?= e($link($pagination['page'] + 1)) ?>">successiva</a>
<?php endif; ?>
</p>
