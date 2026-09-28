<section class="sarva-auth-page">
    <div class="container">
        <div class="sarva-auth-card">
            <div class="text-center mb-3">
                <h1 style="margin:0;font-size:1.5rem;color:#031D4F;font-weight:800;">سروا <span style="color:#05B18B;">دنتال</span></h1>
                <p style="margin:6px 0 0;color:#6b7280;font-size:.95rem;">ورود یا ثبت‌نام با شماره موبایل</p>
            </div>

            <div class="sarva-auth-switch" data-mode="login" id="authSwitch">
                <span class="pill" aria-hidden="true"></span>
                <button type="button" class="active" data-mode="login">ورود</button>
                <button type="button" data-mode="register">ثبت‌نام</button>
            </div>

            <div id="authAlert" class="auth-alert" style="display:none;" role="alert"></div>

            <!-- Step: mobile -->
            <div class="auth-step" data-step="mobile">
                <label class="form-label" for="authMobile">شماره موبایل</label>
                <input type="tel" id="authMobile" class="form-control" inputmode="numeric" maxlength="16" placeholder="09xxxxxxxxx" autocomplete="tel" dir="ltr" style="text-align:left;">
                <button type="button" class="btn btn-primary w-100 mt-3" id="btnRequestOtp" style="background:#031D4F;border-color:#031D4F;border-radius:14px;padding:12px;">
                    دریافت کد تأیید
                </button>
                <p class="text-muted small mt-3 mb-0 text-center" id="modeHint">اگر قبلاً ثبت‌نام کرده‌اید، کد را وارد کنید تا وارد شوید.</p>
            </div>

            <!-- Step: OTP — one real input + visual boxes -->
            <div class="auth-step" data-step="otp" style="display:none;">
                <p class="text-center mb-2" style="color:#031D4F;">کد ۶ رقمی ارسال‌شده به <strong id="otpMobileLabel" dir="ltr"></strong> را وارد کنید</p>
                <label class="visually-hidden" for="otp">کد تأیید</label>
                <div class="otp-field" id="otpField">
                    <div class="otp-inputs" id="otpBoxes" aria-hidden="true">
                        <span class="otp-box" data-index="0"></span>
                        <span class="otp-box" data-index="1"></span>
                        <span class="otp-box" data-index="2"></span>
                        <span class="otp-box" data-index="3"></span>
                        <span class="otp-box" data-index="4"></span>
                        <span class="otp-box" data-index="5"></span>
                    </div>
                    <input
                        type="text"
                        id="otp"
                        name="otp"
                        class="otp-real-input"
                        inputmode="numeric"
                        autocomplete="one-time-code"
                        pattern="[0-9]*"
                        maxlength="6"
                        enterkeyhint="done"
                        autocapitalize="off"
                        autocorrect="off"
                        spellcheck="false"
                        dir="ltr"
                        aria-label="کد تأیید"
                    >
                </div>
                <button type="button" class="btn btn-primary w-100" id="btnVerifyOtp" style="background:#05B18B;border-color:#05B18B;border-radius:14px;padding:12px;">
                    تأیید کد
                </button>
                <div class="auth-meta">
                    <button type="button" class="btn btn-link p-0" id="btnBackMobile" style="color:#6b7280;">تغییر شماره</button>
                    <span id="otpCountdown">ارسال مجدد تا <strong>۶۰</strong> ثانیه</span>
                    <button type="button" class="btn btn-link p-0" id="btnResend" style="display:none;color:#05B18B;">ارسال مجدد</button>
                </div>
            </div>

            <!-- Step: profile (new users) -->
            <div class="auth-step" data-step="profile" style="display:none;">
                <p class="mb-3" style="color:#031D4F;">برای تکمیل ثبت‌نام، نام خود را وارد کنید.</p>
                <label class="form-label" for="authFirst">نام</label>
                <input type="text" id="authFirst" class="form-control mb-2" autocomplete="given-name">
                <label class="form-label" for="authLast">نام خانوادگی</label>
                <input type="text" id="authLast" class="form-control mb-3" autocomplete="family-name">
                <button type="button" class="btn btn-primary w-100" id="btnRegister" style="background:#031D4F;border-color:#031D4F;border-radius:14px;padding:12px;">
                    ثبت‌نام و ورود
                </button>
            </div>
        </div>
    </div>
</section>

<script>
(function () {
    var OTP_LENGTH = 6;
    var mode = 'login';
    var mobile = '';
    var countdownTimer = null;
    var secondsLeft = 0;
    var isRequesting = false;
    var isVerifying = false;
    var webOtpAbort = null;

    var switchEl = document.getElementById('authSwitch');
    var alertEl = document.getElementById('authAlert');
    var modeHint = document.getElementById('modeHint');
    var otpInput = document.getElementById('otp');
    var otpBoxes = Array.prototype.slice.call(document.querySelectorAll('#otpBoxes .otp-box'));
    var otpField = document.getElementById('otpField');
    var btnRequest = document.getElementById('btnRequestOtp');
    var btnResend = document.getElementById('btnResend');
    var btnVerify = document.getElementById('btnVerifyOtp');
    var requestLabel = 'دریافت کد تأیید';

    function csrf() {
        return (window.SARVA && window.SARVA.csrf) ? window.SARVA.csrf : '';
    }

    function apiUrl(path) {
        var base = (window.SARVA && window.SARVA.url) ? String(window.SARVA.url).replace(/\/$/, '') : '';
        return base + path;
    }

    function showAlert(message, type) {
        if (!message) {
            alertEl.style.display = 'none';
            alertEl.textContent = '';
            return;
        }
        alertEl.className = 'auth-alert ' + (type || 'error');
        alertEl.textContent = message;
        alertEl.style.display = 'block';
    }

    function setMode(next) {
        mode = next;
        switchEl.setAttribute('data-mode', next);
        switchEl.querySelectorAll('button').forEach(function (btn) {
            btn.classList.toggle('active', btn.getAttribute('data-mode') === next);
        });
        modeHint.textContent = next === 'register'
            ? 'با شماره موبایل کد دریافت کنید؛ در صورت جدید بودن، نام را وارد می‌کنید.'
            : 'اگر قبلاً ثبت‌نام کرده‌اید، کد را وارد کنید تا وارد شوید.';
    }

    function abortWebOtp() {
        if (webOtpAbort) {
            try { webOtpAbort.abort(); } catch (e) {}
            webOtpAbort = null;
        }
    }

    function showStep(name) {
        document.querySelectorAll('.auth-step').forEach(function (el) {
            el.style.display = el.getAttribute('data-step') === name ? '' : 'none';
        });
        showAlert('');
        if (name !== 'otp') abortWebOtp();
    }

    function cleanOtp(raw) {
        return String(raw || '').replace(/\D/g, '').slice(0, OTP_LENGTH);
    }

    function getOtpCode() {
        return cleanOtp(otpInput.value);
    }

    function renderOtpBoxes() {
        var code = getOtpCode();
        otpBoxes.forEach(function (box, i) {
            var ch = code.charAt(i) || '';
            box.textContent = ch;
            box.classList.toggle('is-filled', ch !== '');
            box.classList.toggle('is-active', i === code.length || (code.length === OTP_LENGTH && i === OTP_LENGTH - 1));
        });
        otpField.classList.toggle('is-complete', code.length === OTP_LENGTH);
        otpField.classList.toggle('is-error', false);
    }

    function setOtpValue(raw, opts) {
        opts = opts || {};
        var next = cleanOtp(raw);
        if (otpInput.value !== next) {
            otpInput.value = next;
        }
        renderOtpBoxes();
        if (opts.autoSubmit !== false && next.length === OTP_LENGTH) {
            verifyOtp();
        }
    }

    function clearOtp() {
        otpInput.value = '';
        renderOtpBoxes();
        otpField.classList.remove('is-error');
    }

    function focusOtp() {
        otpInput.focus();
        try {
            var len = otpInput.value.length;
            otpInput.setSelectionRange(len, len);
        } catch (e) {}
    }

    function listenWebOtp() {
        abortWebOtp();
        if (!('OTPCredential' in window) || !navigator.credentials) return;
        webOtpAbort = new AbortController();
        navigator.credentials.get({
            otp: { transport: ['sms'] },
            signal: webOtpAbort.signal
        }).then(function (cred) {
            if (cred && cred.code) setOtpValue(cred.code);
        }).catch(function () {});
    }

    function startCountdown(sec) {
        secondsLeft = sec || 60;
        var label = document.getElementById('otpCountdown');
        label.style.display = '';
        btnResend.style.display = 'none';
        if (countdownTimer) clearInterval(countdownTimer);
        function tick() {
            label.innerHTML = 'ارسال مجدد تا <strong>' + secondsLeft + '</strong> ثانیه';
            if (secondsLeft <= 0) {
                clearInterval(countdownTimer);
                label.style.display = 'none';
                btnResend.style.display = '';
                return;
            }
            secondsLeft -= 1;
        }
        tick();
        countdownTimer = setInterval(tick, 1000);
    }

    async function postForm(path, data) {
        var body = new URLSearchParams();
        body.set('_csrf', csrf());
        Object.keys(data).forEach(function (k) { body.set(k, data[k]); });
        var res = await fetch(apiUrl(path), {
            method: 'POST',
            headers: { 'Accept': 'application/json', 'Content-Type': 'application/x-www-form-urlencoded' },
            body: body.toString(),
            credentials: 'same-origin'
        });
        var json = {};
        try { json = await res.json(); } catch (e) { json = { ok: false, message: 'پاسخ نامعتبر از سرور' }; }
        return { status: res.status, data: json };
    }

    function normalizeMobileClient(raw) {
        var digits = String(raw || '').replace(/[^\d]/g, '');
        if (digits.indexOf('98') === 0 && digits.length === 12) {
            digits = '0' + digits.slice(2);
        }
        if (digits.length === 10 && digits.charAt(0) === '9') {
            digits = '0' + digits;
        }
        return /^09\d{9}$/.test(digits) ? digits : null;
    }

    async function requestOtp() {
        if (isRequesting) return;
        var raw = (document.getElementById('authMobile').value || '').trim();
        var normalized = normalizeMobileClient(raw);
        if (!normalized) {
            showAlert('شماره موبایل معتبر نیست. مثال: 09123456789', 'error');
            return;
        }
        isRequesting = true;
        btnRequest.disabled = true;
        btnResend.disabled = true;
        btnRequest.textContent = 'در حال ارسال...';
        showAlert('');
        try {
            var result = await postForm('/api/auth/otp/request', { mobile: normalized });
            if (!result.data.ok) {
                showAlert(result.data.message || 'ارسال کد تأیید انجام نشد. لطفاً دوباره تلاش کنید.', 'error');
                return;
            }
            mobile = normalized;
            document.getElementById('authMobile').value = normalized;
            document.getElementById('otpMobileLabel').textContent = mobile;
            clearOtp();
            showStep('otp');
            startCountdown(result.data.resend_in || 60);
            listenWebOtp();
            focusOtp();
            if (result.data.dev_code) {
                showAlert('حالت توسعه: کد تأیید شما ' + result.data.dev_code + ' است (پیامک واقعی ارسال نمی‌شود).', 'success');
                setOtpValue(result.data.dev_code, { autoSubmit: false });
            } else {
                showAlert(result.data.message || 'کد تأیید ارسال شد.', 'success');
            }
        } catch (e) {
            showAlert('خطا در ارتباط با سرور.', 'error');
        } finally {
            isRequesting = false;
            btnRequest.disabled = false;
            btnResend.disabled = false;
            btnRequest.textContent = requestLabel;
        }
    }

    async function verifyOtp() {
        if (isVerifying) return;
        var code = getOtpCode();
        if (code.length !== OTP_LENGTH) {
            showAlert('لطفاً هر ۶ رقم کد را وارد کنید.', 'error');
            otpField.classList.add('is-error');
            focusOtp();
            return;
        }
        isVerifying = true;
        btnVerify.disabled = true;
        try {
            var result = await postForm('/api/auth/otp/verify', { mobile: mobile, code: code });
            if (!result.data.ok) {
                showAlert(result.data.message || 'کد واردشده صحیح نیست.', 'error');
                otpField.classList.add('is-error');
                focusOtp();
                return;
            }
            abortWebOtp();
            if (result.data.is_new || result.data.needs_profile) {
                showStep('profile');
                showAlert('شماره تأیید شد. اطلاعات خود را تکمیل کنید.', 'success');
                return;
            }
            window.location.href = result.data.redirect || apiUrl('/patient');
        } catch (e) {
            showAlert('خطا در تأیید کد.', 'error');
        } finally {
            isVerifying = false;
            btnVerify.disabled = false;
        }
    }

    async function register() {
        var first = (document.getElementById('authFirst').value || '').trim();
        var last = (document.getElementById('authLast').value || '').trim();
        if (!first || !last) {
            showAlert('نام و نام خانوادگی الزامی است.', 'error');
            return;
        }
        var btn = document.getElementById('btnRegister');
        btn.disabled = true;
        try {
            var result = await postForm('/api/auth/register', { first_name: first, last_name: last });
            if (!result.data.ok) {
                showAlert(result.data.message || 'ثبت‌نام ناموفق بود.', 'error');
                return;
            }
            window.location.href = result.data.redirect || apiUrl('/patient');
        } catch (e) {
            showAlert('خطا در ثبت‌نام.', 'error');
        } finally {
            btn.disabled = false;
        }
    }

    switchEl.querySelectorAll('button').forEach(function (btn) {
        btn.addEventListener('click', function () {
            setMode(btn.getAttribute('data-mode'));
            showStep('mobile');
        });
    });

    btnRequest.addEventListener('click', requestOtp);
    btnVerify.addEventListener('click', verifyOtp);
    document.getElementById('btnRegister').addEventListener('click', register);
    btnResend.addEventListener('click', requestOtp);
    document.getElementById('btnBackMobile').addEventListener('click', function () {
        abortWebOtp();
        showStep('mobile');
    });

    otpField.addEventListener('click', function () { focusOtp(); });

    otpInput.addEventListener('input', function () {
        setOtpValue(otpInput.value);
    });
    otpInput.addEventListener('change', function () {
        setOtpValue(otpInput.value);
    });
    otpInput.addEventListener('paste', function (e) {
        e.preventDefault();
        var text = (e.clipboardData || window.clipboardData).getData('text');
        setOtpValue(text);
    });
    otpInput.addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            verifyOtp();
        }
    });
    otpInput.addEventListener('focus', function () {
        renderOtpBoxes();
    });

    document.getElementById('authMobile').addEventListener('keydown', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            requestOtp();
        }
    });

    ['authFirst', 'authLast'].forEach(function (id) {
        document.getElementById(id).addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                register();
            }
        });
    });

    renderOtpBoxes();
    setMode('login');
})();
</script>
