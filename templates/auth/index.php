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

            <!-- Step: OTP -->
            <div class="auth-step" data-step="otp" style="display:none;">
                <p class="text-center mb-2" style="color:#031D4F;">کد ۶ رقمی ارسال‌شده به <strong id="otpMobileLabel" dir="ltr"></strong> را وارد کنید</p>
                <div class="otp-inputs" id="otpInputs">
                    <input type="text" inputmode="numeric" maxlength="1" aria-label="رقم ۱" autocomplete="one-time-code">
                    <input type="text" inputmode="numeric" maxlength="1" aria-label="رقم ۲">
                    <input type="text" inputmode="numeric" maxlength="1" aria-label="رقم ۳">
                    <input type="text" inputmode="numeric" maxlength="1" aria-label="رقم ۴">
                    <input type="text" inputmode="numeric" maxlength="1" aria-label="رقم ۵">
                    <input type="text" inputmode="numeric" maxlength="1" aria-label="رقم ۶">
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
    var mode = 'login';
    var mobile = '';
    var countdownTimer = null;
    var secondsLeft = 0;

    var switchEl = document.getElementById('authSwitch');
    var alertEl = document.getElementById('authAlert');
    var modeHint = document.getElementById('modeHint');
    var otpInputs = Array.prototype.slice.call(document.querySelectorAll('#otpInputs input'));

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

    function showStep(name) {
        document.querySelectorAll('.auth-step').forEach(function (el) {
            el.style.display = el.getAttribute('data-step') === name ? '' : 'none';
        });
        showAlert('');
    }

    function getOtpCode() {
        return otpInputs.map(function (i) { return i.value.replace(/\D/g, ''); }).join('');
    }

    function clearOtp() {
        otpInputs.forEach(function (i) { i.value = ''; });
        if (otpInputs[0]) otpInputs[0].focus();
    }

    function startCountdown(sec) {
        secondsLeft = sec || 60;
        var label = document.getElementById('otpCountdown');
        var resend = document.getElementById('btnResend');
        label.style.display = '';
        resend.style.display = 'none';
        if (countdownTimer) clearInterval(countdownTimer);
        function tick() {
            label.innerHTML = 'ارسال مجدد تا <strong>' + secondsLeft + '</strong> ثانیه';
            if (secondsLeft <= 0) {
                clearInterval(countdownTimer);
                label.style.display = 'none';
                resend.style.display = '';
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
        var raw = (document.getElementById('authMobile').value || '').trim();
        var normalized = normalizeMobileClient(raw);
        if (!normalized) {
            showAlert('شماره موبایل معتبر نیست. مثال: 09123456789', 'error');
            return;
        }
        var btn = document.getElementById('btnRequestOtp');
        btn.disabled = true;
        showAlert('');
        try {
            var result = await postForm('/api/auth/otp/request', { mobile: normalized });
            if (!result.data.ok) {
                showAlert(result.data.message || 'ارسال کد ناموفق بود.', 'error');
                return;
            }
            mobile = normalized;
            document.getElementById('authMobile').value = normalized;
            document.getElementById('otpMobileLabel').textContent = mobile;
            clearOtp();
            showStep('otp');
            startCountdown(result.data.resend_in || 60);
            if (result.data.dev_code) {
                showAlert('حالت توسعه: کد تأیید شما ' + result.data.dev_code + ' است (پیامک واقعی ارسال نمی‌شود).', 'success');
                // Prefill OTP boxes for convenience in demo mode
                var digits = String(result.data.dev_code).split('');
                otpInputs.forEach(function (input, i) {
                    input.value = digits[i] || '';
                });
            } else {
                showAlert(result.data.message || 'کد تأیید ارسال شد.', 'success');
            }
        } catch (e) {
            showAlert('خطا در ارتباط با سرور.', 'error');
        } finally {
            btn.disabled = false;
        }
    }

    async function verifyOtp() {
        var code = getOtpCode();
        if (code.length !== 6) {
            showAlert('لطفاً هر ۶ رقم کد را وارد کنید.', 'error');
            return;
        }
        var btn = document.getElementById('btnVerifyOtp');
        btn.disabled = true;
        try {
            var result = await postForm('/api/auth/otp/verify', { mobile: mobile, code: code });
            if (!result.data.ok) {
                showAlert(result.data.message || 'کد نادرست است.', 'error');
                return;
            }
            if (result.data.is_new || result.data.needs_profile) {
                showStep('profile');
                showAlert('شماره تأیید شد. اطلاعات خود را تکمیل کنید.', 'success');
                return;
            }
            window.location.href = result.data.redirect || apiUrl('/patient');
        } catch (e) {
            showAlert('خطا در تأیید کد.', 'error');
        } finally {
            btn.disabled = false;
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

    document.getElementById('btnRequestOtp').addEventListener('click', requestOtp);
    document.getElementById('btnVerifyOtp').addEventListener('click', verifyOtp);
    document.getElementById('btnRegister').addEventListener('click', register);
    document.getElementById('btnResend').addEventListener('click', requestOtp);
    document.getElementById('btnBackMobile').addEventListener('click', function () {
        showStep('mobile');
    });

    otpInputs.forEach(function (input, idx) {
        input.addEventListener('input', function () {
            input.value = input.value.replace(/\D/g, '').slice(0, 1);
            if (input.value && otpInputs[idx + 1]) otpInputs[idx + 1].focus();
            if (getOtpCode().length === 6) verifyOtp();
        });
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                verifyOtp();
                return;
            }
            if (e.key === 'Backspace' && !input.value && otpInputs[idx - 1]) {
                otpInputs[idx - 1].focus();
            }
        });
        input.addEventListener('paste', function (e) {
            e.preventDefault();
            var text = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '').slice(0, 6);
            text.split('').forEach(function (ch, i) {
                if (otpInputs[i]) otpInputs[i].value = ch;
            });
            if (text.length === 6) verifyOtp();
            else if (otpInputs[text.length]) otpInputs[text.length].focus();
        });
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

    setMode('login');
})();
</script>
