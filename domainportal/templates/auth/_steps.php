<?php
function steps(int $current): void {
    $labels = ['Konto', 'Daten', 'Zahlung'];
    echo '<ol class="steps container narrow" aria-label="Fortschritt">';
    foreach ($labels as $i => $l) {
        $n = $i + 1;
        $cls = $n < $current ? 'done' : ($n === $current ? 'current' : '');
        echo '<li class="' . $cls . '"' . ($n === $current ? ' aria-current="step"' : '') . '><span>' . ($n < $current ? '✓' : $n) . '</span>' . e($l) . '</li>';
    }
    echo '</ol>';
}
