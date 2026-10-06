<?php
/**
 * Summary of the errors at the top of a form, each linked to its field.
 *
 * @var array<string, string> $errors messages by field
 */
$anchors = ['guests' => 'f-adults', 'apartment_id' => null, 'form' => null, 'locale' => null];
?>
<?php if ($errors !== []): ?>
<div class="alert alert-error" role="alert" id="form-errors">
    <p><strong><?= e(t('flow.errors_intro')) ?></strong></p>
    <ul>
<?php foreach ($errors as $field => $message): ?>
<?php $anchor = array_key_exists($field, $anchors) ? $anchors[$field] : 'f-' . $field; ?>
        <li><?php if ($anchor !== null): ?><a href="#<?= e($anchor) ?>"><?= e($message) ?></a><?php else: ?><?= e($message) ?><?php endif; ?></li>
<?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>
