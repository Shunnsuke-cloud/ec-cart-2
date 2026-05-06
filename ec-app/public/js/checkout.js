(function () {
    const form = document.getElementById('order-submit-form');
    if (!form) {
        return;
    }

    const tokenInput = document.getElementById('card-token');
    const errorBox = document.getElementById('token-error');
    const submitButton = form.querySelector('button[type="submit"]');
    const appConfig = window.APP_CONFIG || {};
    const payjpPublicKey = (appConfig.payjpPublicKey || '').trim();

    if (typeof window.Payjp === 'undefined') {
        showError('決済ライブラリの読み込みに失敗しました。ページを再読み込みしてください。');
        if (submitButton) {
            submitButton.disabled = true;
        }
        return;
    }

    if (!payjpPublicKey) {
        showError('決済設定が未設定です。管理者にお問い合わせください。');
        if (submitButton) {
            submitButton.disabled = true;
        }
        return;
    }

    Payjp.setPublicKey(payjpPublicKey);

    form.addEventListener('submit', function (event) {
        event.preventDefault();
        hideError();

        const card = {
            number: valueOf('card-number').replace(/\s+/g, ''),
            exp_month: valueOf('card-exp-month'),
            exp_year: normalizeYear(valueOf('card-exp-year')),
            cvc: valueOf('card-cvc'),
            name: valueOf('card-name'),
        };

        if (!isCardInputValid(card)) {
            showError('カード情報を正しく入力してください。');
            return;
        }

        if (submitButton) {
            submitButton.disabled = true;
        }

        Payjp.createToken(card, function (status, response) {
            if (status !== 200 || !response || !response.id) {
                const message = response && response.error && response.error.message
                    ? response.error.message
                    : 'カードトークンの生成に失敗しました。';
                showError(message);
                if (submitButton) {
                    submitButton.disabled = false;
                }
                return;
            }

            tokenInput.value = response.id;
            clearCardInputs();
            form.submit();
        });
    });

    function valueOf(id) {
        const el = document.getElementById(id);
        return el ? el.value.trim() : '';
    }

    function normalizeYear(year) {
        if (year.length === 4 && year.startsWith('20')) {
            return year.slice(2);
        }
        return year;
    }

    function isCardInputValid(card) {
        return card.number !== ''
            && card.exp_month !== ''
            && card.exp_year !== ''
            && card.cvc !== ''
            && card.name !== '';
    }

    function clearCardInputs() {
        ['card-number', 'card-exp-month', 'card-exp-year', 'card-cvc', 'card-name'].forEach(function (id) {
            const el = document.getElementById(id);
            if (el) {
                el.value = '';
            }
        });
    }

    function showError(message) {
        if (!errorBox) {
            return;
        }
        errorBox.hidden = false;
        errorBox.textContent = message;
    }

    function hideError() {
        if (!errorBox) {
            return;
        }
        errorBox.hidden = true;
        errorBox.textContent = '';
    }
})();
