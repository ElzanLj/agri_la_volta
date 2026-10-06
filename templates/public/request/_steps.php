<?php
/** @var int $step current step, 1 to 4 */
?>
<nav class="steps" aria-label="<?= e(t('flow.step.label')) ?>">
    <ol>
<?php for ($i = 1; $i <= 4; $i++): ?>
        <li<?= $i === $step ? ' aria-current="step"' : '' ?>><?= e(t('flow.step.' . $i)) ?></li>
<?php endfor; ?>
    </ol>
</nav>
