'use strict';

(() => {
  const form = document.querySelector('#feedback-form');
  if (!form) return;

  const notice = document.querySelector('#form-notice');
  const submitButton = document.querySelector('#submit-button');
  const captchaImage = document.querySelector('#captcha-image');
  const captchaRefresh = document.querySelector('#captcha-refresh');
  const phoneInput = document.querySelector('#phone');
  const messageInput = document.querySelector('#message');
  const messageCounter = document.querySelector('#message-counter');

  const fields = ['topic', 'full_name', 'phone', 'email', 'message', 'captcha', 'consent'];
  const topics = new Set(['general', 'partnership', 'support', 'other']);
  const countCharacters = (value) => Array.from(value).length;

  function clearError(name) {
    const group = form.querySelector(`[data-field="${name}"]`);
    if (!group) return;
    const input = form.elements.namedItem(name);
    const error = group.querySelector('.field-error');
    group.classList.remove('has-error');
    input?.removeAttribute('aria-invalid');
    if (error) {
      error.textContent = '';
      error.hidden = true;
    }
  }

  function showError(name, text) {
    const group = form.querySelector(`[data-field="${name}"]`);
    if (!group) return;
    group.classList.add('has-error');
    const input = form.elements.namedItem(name);
    input?.setAttribute('aria-invalid', 'true');
    const error = group.querySelector('.field-error');
    if (error) {
      error.textContent = text; // Never inject server-controlled HTML.
      error.hidden = false;
    }
  }

  function showNotice(message, success) {
    notice.textContent = message;
    notice.className = `notice ${success ? 'notice-success' : 'notice-error'}`;
    notice.setAttribute('role', success ? 'status' : 'alert');
    notice.hidden = false;
  }

  function clearNotice() {
    notice.hidden = true;
    notice.textContent = '';
  }

  function refreshCaptcha() {
    captchaImage.src = `/api/captcha.php?v=${Date.now()}`;
    form.elements.namedItem('captcha').value = '';
    clearError('captcha');
  }

  function formatPhone(value) {
    let digits = value.replace(/\D/g, '');
    if (!digits) return '';
    if (digits.startsWith('7') || digits.startsWith('8')) digits = digits.slice(1);
    digits = digits.slice(0, 10);
    let result = '+7';
    if (digits.length > 0) result += ` (${digits.slice(0, 3)}`;
    if (digits.length >= 3) result += ')';
    if (digits.length > 3) result += ` ${digits.slice(3, 6)}`;
    if (digits.length > 6) result += `-${digits.slice(6, 8)}`;
    if (digits.length > 8) result += `-${digits.slice(8, 10)}`;
    return result;
  }

  function validate() {
    const data = new FormData(form);
    const errors = {};
    const value = (name) => String(data.get(name) ?? '').trim();

    if (!value('topic')) errors.topic = 'Выберите тему обращения.';
    else if (!topics.has(value('topic'))) errors.topic = 'Выбрана неизвестная тема обращения.';

    if (!value('full_name')) errors.full_name = 'Укажите ваше ФИО.';
    else if (countCharacters(value('full_name')) > 255) errors.full_name = 'ФИО должно содержать не более 255 символов.';

    const phone = value('phone');
    if (!phone) errors.phone = 'Укажите номер телефона.';
    else if (countCharacters(phone) > 255) errors.phone = 'Номер телефона слишком длинный.';
    else if (!/^(?:\+7|8)\d{10}$/.test(phone.replace(/[\s()-]+/g, ''))) {
      errors.phone = 'Введите российский номер в формате +7 (999) 123-45-67.';
    }

    const email = value('email');
    if (!email) errors.email = 'Укажите электронную почту.';
    else if (countCharacters(email) > 255) errors.email = 'Адрес почты должен содержать не более 255 символов.';
    else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email) || !form.elements.namedItem('email').checkValidity()) {
      errors.email = 'Укажите корректный адрес электронной почты.';
    }

    if (!value('message')) errors.message = 'Напишите сообщение.';
    else if (countCharacters(value('message')) > 4096) errors.message = 'Сообщение должно содержать не более 4096 символов.';

    if (!/^[a-z2-9]{6}$/i.test(value('captcha'))) {
      errors.captcha = value('captcha') ? 'Код должен состоять из шести символов.' : 'Введите код с изображения.';
    }
    if (data.get('consent') !== '1') errors.consent = 'Необходимо согласие на обработку персональных данных.';

    return errors;
  }

  function renderErrors(errors) {
    fields.forEach(clearError);
    Object.entries(errors).forEach(([name, text]) => {
      if (fields.includes(name)) showError(name, text);
    });
    const firstInvalid = fields.find((name) => errors[name]);
    if (firstInvalid) form.elements.namedItem(firstInvalid)?.focus();
  }

  function updateCounter() {
    messageCounter.textContent = `${countCharacters(messageInput.value)} / 4096`;
  }

  phoneInput.addEventListener('input', () => {
    phoneInput.value = formatPhone(phoneInput.value);
    clearError('phone');
  });
  messageInput.addEventListener('input', updateCounter);
  captchaRefresh.addEventListener('click', refreshCaptcha);

  fields.forEach((name) => {
    const input = form.elements.namedItem(name);
    const eventName = name === 'consent' || name === 'topic' ? 'change' : 'input';
    input.addEventListener(eventName, () => {
      clearError(name);
      if (!notice.hidden && notice.classList.contains('notice-error')) clearNotice();
    });
  });

  form.addEventListener('submit', async (event) => {
    event.preventDefault();
    clearNotice();
    const errors = validate();
    renderErrors(errors);
    if (Object.keys(errors).length > 0) {
      showNotice('Проверьте выделенные поля и отправьте форму ещё раз.', false);
      return;
    }

    submitButton.disabled = true;
    submitButton.querySelector('.submit-label').textContent = 'Отправляем...';
    form.setAttribute('aria-busy', 'true');

    try {
      const response = await fetch(form.getAttribute('action'), {
        method: 'POST',
        body: new FormData(form),
        headers: { Accept: 'application/json' },
        credentials: 'same-origin',
      });
      const data = await response.json();

      if (data.captcha_refresh) refreshCaptcha();
      if (!response.ok || !data.success) {
        renderErrors(data.errors && typeof data.errors === 'object' ? data.errors : {});
        showNotice(data.message || 'Не удалось отправить сообщение.', false);
        return;
      }

      form.reset();
      updateCounter();
      fields.forEach(clearError);
      showNotice(data.message || 'Сообщение успешно отправлено.', true);
      // Keep the new CAPTCHA already requested above; reset does not change its image.
    } catch (_) {
      refreshCaptcha();
      showNotice('Не удалось связаться с сервером. Проверьте соединение и попробуйте ещё раз.', false);
    } finally {
      submitButton.disabled = false;
      submitButton.querySelector('.submit-label').textContent = 'Отправить сообщение';
      form.removeAttribute('aria-busy');
    }
  });
})();
