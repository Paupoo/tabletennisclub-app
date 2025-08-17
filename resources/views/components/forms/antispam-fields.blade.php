@props(['start' => time()])

{{-- Champ honeypot (invisible pour l'utilisateur) --}}
<div style="display:none;" aria-hidden="true">
    <label for="website">Website</label>
    <input type="text" name="website" id="website" tabindex="-1" autocomplete="off">
</div>

{{-- Champ horodatage (temps de génération du formulaire) --}}
<input type="hidden" name="form_start" value="{{ $start }}">
