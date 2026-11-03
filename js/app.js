/*
 * Scripts do MovieStar. Nada de JavaScript inline (onclick, onsubmit...): a Content-Security-Policy
 * do .htaccess só permite scripts de arquivos.
 */
(function () {
    'use strict';

    // Formulários com data-confirm="mensagem" pedem confirmação antes de enviar (ex.: excluir filme).
    document.addEventListener('submit', function (event) {
        var form = event.target;
        var message = form instanceof HTMLFormElement ? form.getAttribute('data-confirm') : null;

        if (message !== null && !window.confirm(message)) {
            event.preventDefault();
        }
    });
})();
