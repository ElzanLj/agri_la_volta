<?php
/**
 * Filter form (GET: it changes nothing, so it needs no CSRF token).
 *
 * @var string $action
 * @var \App\Http\Admin\ListFilters $filters
 * @var array<string, string>|null $statuses value => label
 * @var list<array<string, mixed>>|null $apartments
 * @var bool|null $withOrigin
 * @var array<string, string>|null $entities
 * @var bool|null $withPeriod
 */
$statuses = $statuses ?? null;
$apartments = $apartments ?? null;
$withOrigin = $withOrigin ?? false;
$entities = $entities ?? null;
$withPeriod = $withPeriod ?? true;
?>
<?php if ($filters->errors !== []): ?>
<div class="alert alert-error" role="alert">
    <p>Filtri non validi:</p>
    <ul>
<?php foreach ($filters->errors as $field => $message): ?>
        <li><?= e($field) ?>: <?= e($message) ?></li>
<?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<form method="get" action="<?= e(url($action)) ?>" class="filters">
<?php if ($statuses !== null): ?>
    <div class="field">
        <label for="f-stato">Stato</label>
        <select id="f-stato" name="stato">
            <option value="">Tutti</option>
<?php foreach ($statuses as $value => $label): ?>
            <option value="<?= e($value) ?>"<?= $filters->status === $value ? ' selected' : '' ?>><?= e($label) ?></option>
<?php endforeach; ?>
        </select>
    </div>
<?php endif; ?>
<?php if ($apartments !== null): ?>
    <div class="field">
        <label for="f-appartamento">Appartamento</label>
        <select id="f-appartamento" name="appartamento">
            <option value="">Tutti</option>
<?php foreach ($apartments as $a): ?>
            <option value="<?= e((string) $a['id']) ?>"<?= $filters->apartmentId === (int) $a['id'] ? ' selected' : '' ?>><?= e($a['name']) ?></option>
<?php endforeach; ?>
        </select>
    </div>
<?php endif; ?>
<?php if ($withOrigin): ?>
    <div class="field">
        <label for="f-origine">Origine</label>
        <select id="f-origine" name="origine">
            <option value="">Tutte</option>
<?php foreach (\App\Http\Admin\Labels::ORIGINS as $value => $label): ?>
            <option value="<?= e($value) ?>"<?= $filters->origin === $value ? ' selected' : '' ?>><?= e($label) ?></option>
<?php endforeach; ?>
        </select>
    </div>
<?php endif; ?>
<?php if ($entities !== null): ?>
    <div class="field">
        <label for="f-tipo">Tipo</label>
        <select id="f-tipo" name="tipo">
            <option value="">Tutti</option>
<?php foreach ($entities as $value => $label): ?>
            <option value="<?= e($value) ?>"<?= $filters->entity === $value ? ' selected' : '' ?>><?= e($label) ?></option>
<?php endforeach; ?>
        </select>
    </div>
<?php endif; ?>
<?php if ($withPeriod): ?>
    <div class="field">
        <label for="f-dal">Soggiorni dal</label>
        <input type="date" id="f-dal" name="dal" value="<?= e((string) $filters->from) ?>">
    </div>
    <div class="field">
        <label for="f-al">al (escluso)</label>
        <input type="date" id="f-al" name="al" value="<?= e((string) $filters->to) ?>">
    </div>
<?php endif; ?>
    <div class="field">
        <button type="submit" class="button">Filtra</button>
    </div>
</form>
