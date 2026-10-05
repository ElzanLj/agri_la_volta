<?php
/** @var list<array<string, mixed>> $apartments */

use App\Http\Admin\Labels;

/** One download form; $withOrigin adds the booking origin filter. */
$form = static function (string $action, string $title, array $statuses, bool $withOrigin) use ($apartments): string {
    $id = preg_replace('/[^a-z]/', '', $action);
    $html = '<h2>' . e($title) . '</h2><form method="get" action="' . e(url($action)) . '" class="filters">';
    $html .= '<div class="field"><label for="' . $id . '-stato">Stato</label><select id="' . $id . '-stato" name="stato"><option value="">Tutti</option>';
    foreach ($statuses as $value => $label) {
        $html .= '<option value="' . e($value) . '">' . e($label) . '</option>';
    }
    $html .= '</select></div>';
    $html .= '<div class="field"><label for="' . $id . '-app">Appartamento</label><select id="' . $id . '-app" name="appartamento"><option value="">Tutti</option>';
    foreach ($apartments as $a) {
        $html .= '<option value="' . e((string) $a['id']) . '">' . e($a['name']) . '</option>';
    }
    $html .= '</select></div>';
    if ($withOrigin) {
        $html .= '<div class="field"><label for="' . $id . '-orig">Origine</label><select id="' . $id . '-orig" name="origine"><option value="">Tutte</option>';
        foreach (Labels::ORIGINS as $value => $label) {
            $html .= '<option value="' . e($value) . '">' . e($label) . '</option>';
        }
        $html .= '</select></div>';
    }
    $html .= '<div class="field"><label for="' . $id . '-dal">Soggiorni dal</label><input type="date" id="' . $id . '-dal" name="dal"></div>';
    $html .= '<div class="field"><label for="' . $id . '-al">al (escluso)</label><input type="date" id="' . $id . '-al" name="al"></div>';
    $html .= '<div class="field"><button type="submit" class="button">Scarica CSV</button></div></form>';
    return $html;
};
?>
<h1>Esportazione dati</h1>
<p>File CSV con separatore «;», aperti da Excel, LibreOffice e Google Sheets. Contengono dati personali dei clienti: conservali con cura. Le celle che potrebbero essere lette come formule vengono precedute da un apostrofo (anche i numeri di telefono che iniziano con «+»).</p>

<?= $form('/admin/export/richieste.csv', 'Richieste', Labels::REQUEST_STATUSES, false) ?>
<?= $form('/admin/export/prenotazioni.csv', 'Prenotazioni', Labels::BOOKING_STATUSES, true) ?>
