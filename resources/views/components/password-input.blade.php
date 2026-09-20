{{--
  A password box with an eye to show and hide what was typed.

  Everything passed to it lands on the input itself, so a caller keeps its own label, hint and
  error markup and nothing else has to change:

      <x-password-input name="password" id="password" required autocomplete="new-password" />

  The icons are inline SVG rather than an icon font, because the admin sign-in page loads none.
  The styles and the script go out once per page however many boxes are on it, and the click is
  handled on the document, so it works no matter where in the page this lands.
--}}
<div class="pw-field">
    <input type="password" {{ $attributes }}>

    <button type="button" class="pw-eye" aria-label="Show password" aria-pressed="false" title="Show password">
        <svg class="pw-on" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
            <circle cx="12" cy="12" r="3"></circle>
        </svg>
        <svg class="pw-off" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"
             stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
            <line x1="1" y1="1" x2="23" y2="23"></line>
        </svg>
    </button>
</div>

@once
<style>
    .pw-field { position: relative; display: block; }

    /* Room for the button, so long passwords never run underneath it. */
    .pw-field input { width: 100%; padding-right: 42px; }

    .pw-eye {
        position: absolute; top: 50%; right: 6px; transform: translateY(-50%);
        display: grid; place-items: center;
        width: 32px; height: 32px; padding: 0; margin: 0;
        background: none; border: 0; border-radius: 6px;
        color: #94a3b8; cursor: pointer; line-height: 0;
    }
    .pw-eye:hover { color: #475569; background: #f1f5f9; }
    .pw-eye:focus-visible { outline: 2px solid #2561e8; outline-offset: 1px; }
    .pw-eye svg { width: 18px; height: 18px; display: block; }

    /* One icon at a time: the open eye while hidden, the crossed-out one while showing. */
    .pw-eye .pw-off { display: none; }
    .pw-eye.is-on .pw-on { display: none; }
    .pw-eye.is-on .pw-off { display: block; }
</style>

<script>
    document.addEventListener('click', function (event) {
        var button = event.target.closest('.pw-eye');
        if (! button) { return; }

        var input = button.parentElement.querySelector('input');
        if (! input) { return; }

        var showing = input.type === 'password';
        input.type  = showing ? 'text' : 'password';

        button.classList.toggle('is-on', showing);
        button.setAttribute('aria-pressed', showing ? 'true' : 'false');
        button.setAttribute('aria-label', showing ? 'Hide password' : 'Show password');
        button.title = showing ? 'Hide password' : 'Show password';
    });
</script>
@endonce
