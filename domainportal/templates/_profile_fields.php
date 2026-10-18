<?php /** @var array $form */ use App\Users; ?>
<div class="seg" role="radiogroup" aria-label="Kundentyp">
  <label><input type="radio" name="type" value="private" <?= ($form['type'] ?? 'private') !== 'company' ? 'checked' : '' ?>> <span>Privatperson</span></label>
  <label><input type="radio" name="type" value="company" <?= ($form['type'] ?? '') === 'company' ? 'checked' : '' ?>> <span>Unternehmen</span></label>
</div>
<label class="field" data-company><span>Firma</span><input name="company" value="<?= e($form['company'] ?? '') ?>" autocomplete="organization"></label>
<div class="row2">
  <label class="field"><span>Vorname</span><input name="first_name" value="<?= e($form['first_name'] ?? '') ?>" required autocomplete="given-name"></label>
  <label class="field"><span>Nachname</span><input name="last_name" value="<?= e($form['last_name'] ?? '') ?>" required autocomplete="family-name"></label>
</div>
<label class="field"><span>Straße und Hausnummer</span><input name="street" value="<?= e($form['street'] ?? '') ?>" required autocomplete="street-address"></label>
<div class="row3">
  <label class="field"><span>PLZ</span><input name="zip" value="<?= e($form['zip'] ?? '') ?>" required autocomplete="postal-code"></label>
  <label class="field"><span>Ort</span><input name="city" value="<?= e($form['city'] ?? '') ?>" required autocomplete="address-level2"></label>
</div>
<div class="row2">
  <label class="field"><span>Land</span>
    <select name="country" required autocomplete="country">
      <?php foreach (Users::COUNTRIES as $c => $n): ?><option value="<?= e($c) ?>" <?= ($form['country'] ?? 'DE') === $c ? 'selected' : '' ?>><?= e($n) ?></option><?php endforeach; ?>
    </select></label>
  <label class="field"><span>Telefon</span><input type="tel" name="phone" value="<?= e($form['phone'] ?? '') ?>" placeholder="+49 …" required autocomplete="tel"></label>
</div>
