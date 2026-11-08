/* ALIAS Kolej Grading System - client-side behaviour (no frameworks). */
(function () {
  'use strict';

  /* ---------- Grading rules (mirror includes/bootstrap.php) ---------- */
  function gradeFor(total) {
    if (total >= 80) return 'A';
    if (total >= 60) return 'B';
    if (total >= 40) return 'C';
    if (total >= 30) return 'D';
    return 'F';
  }

  function fmt(n) {
    return String(Math.round(n * 100) / 100);
  }

  /* ---------- Field error helpers ---------- */
  function errorEl(input) {
    return document.getElementById(input.id + '-error');
  }

  function setError(input, msg) {
    var el = errorEl(input);
    if (msg) {
      input.setAttribute('aria-invalid', 'true');
      if (el) { el.textContent = msg; el.hidden = false; }
    } else {
      input.removeAttribute('aria-invalid');
      if (el) { el.textContent = ''; el.hidden = true; }
    }
  }

  function labelOf(input) {
    if (input.dataset.label) return input.dataset.label;
    var l = document.querySelector('label[for="' + input.id + '"]');
    return l ? l.childNodes[0].textContent.trim() : 'This field';
  }

  /** Synchronous validation of one input. Returns an error message or ''. */
  function validateInput(input) {
    var v = input.value.trim();
    var label = labelOf(input);

    if (input.required && v === '') return label + ' is required.';
    if (v === '') return '';

    if (input.type === 'number') {
      if (!/^\d{1,3}(\.\d{1,2})?$/.test(v)) {
        return label + ' must be a number from 0 to ' + input.max + ' (up to 2 decimal places).';
      }
      var n = parseFloat(v);
      if (input.min !== '' && n < parseFloat(input.min)) return label + ' cannot be less than ' + input.min + '.';
      if (input.max !== '' && n > parseFloat(input.max)) return label + ' cannot be more than ' + input.max + '.';
    }

    if (input.minLength > 0 && v.length < input.minLength) {
      return label + ' must be at least ' + input.minLength + ' characters.';
    }

    if (input.dataset.pattern) {
      var re;
      try { re = new RegExp('^(?:' + input.dataset.pattern + ')$', 'u'); } catch (e) { re = null; }
      if (re && !re.test(v)) return input.dataset.patternMsg || label + ' is not in the correct format.';
    }

    if (input.dataset.match) {
      var other = document.getElementById(input.dataset.match);
      if (other && other.value !== input.value) return 'Passwords do not match.';
    }

    if (input.dataset.serverError) return input.dataset.serverError;
    return '';
  }

  /* ---------- Matric number uniqueness (AJAX) ---------- */
  function checkMatric(input, excludeId) {
    var v = input.value.trim().toUpperCase();
    delete input.dataset.serverError;
    var formatError = validateInput(input);
    if (formatError) {
      setError(input, formatError);
      return Promise.resolve(false);
    }

    var url = 'check_matric.php?matric=' + encodeURIComponent(v) + '&exclude=' + encodeURIComponent(excludeId || 0);
    return fetch(url, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then(function (r) { return r.ok ? r.json() : { available: null }; })
      .then(function (data) {
        if (data.available === false) {
          input.dataset.serverError = 'This matric number is already registered.';
          setError(input, input.dataset.serverError);
          return false;
        }
        setError(input, validateInput(input));
        return true;
      })
      .catch(function () { return true; }); // network issue: let the server decide
  }

  /* ---------- Generic form validation ---------- */
  document.querySelectorAll('form[data-validate]').forEach(function (form) {
    var inputs = Array.prototype.slice.call(form.querySelectorAll('input:not([type=hidden]), select, textarea'));
    var matric = form.querySelector('#matric_no');
    var excludeId = form.dataset.excludeId || 0;
    var verified = false;

    inputs.forEach(function (input) {
      // Validate when leaving a field, then live once it has been touched.
      input.addEventListener('blur', function () {
        input.dataset.touched = '1';
        if (input === matric && matric.value.trim() !== '') return;
        setError(input, validateInput(input));
      });
      input.addEventListener('input', function () {
        if (input === matric) delete input.dataset.serverError;
        if (input.dataset.touched) setError(input, validateInput(input));
        // Re-check a "confirm password" field when its partner changes.
        inputs.forEach(function (other) {
          if (other.dataset.match === input.id && other.dataset.touched) setError(other, validateInput(other));
        });
      });
    });

    if (matric) {
      matric.addEventListener('blur', function () {
        matric.value = matric.value.trim().toUpperCase();
        if (matric.value !== '') checkMatric(matric, excludeId);
      });
    }

    form.addEventListener('submit', function (ev) {
      if (verified) return; // second pass after async checks succeeded

      var firstBad = null;
      inputs.forEach(function (input) {
        input.dataset.touched = '1';
        var msg = validateInput(input);
        setError(input, msg);
        if (msg && !firstBad) firstBad = input;
      });

      if (firstBad) {
        ev.preventDefault();
        firstBad.focus();
        return;
      }

      if (matric) {
        ev.preventDefault();
        var submitter = ev.submitter || null;
        checkMatric(matric, excludeId).then(function (ok) {
          if (!ok) { matric.focus(); return; }
          verified = true;
          if (form.requestSubmit) form.requestSubmit(submitter); else form.submit();
        });
      }
    });
  });

  /* ---------- Live total / grade preview ---------- */
  var studentForm = document.querySelector('form[data-student-form]');
  if (studentForm) {
    var markInputs = studentForm.querySelectorAll('input[data-mark]');
    var totalEl = document.getElementById('preview-total');
    var gradeEl = document.getElementById('preview-grade');
    var barEl = document.getElementById('preview-bar');
    var noteEl = document.getElementById('preview-note');
    var scaleRows = document.querySelectorAll('[data-scale]');

    var updatePreview = function () {
      var total = 0, filled = 0, invalid = false;
      markInputs.forEach(function (input) {
        var v = input.value.trim();
        if (v === '') return;
        var n = parseFloat(v);
        if (isNaN(n) || n < 0 || n > parseFloat(input.max)) { invalid = true; return; }
        total += n;
        filled++;
      });

      total = Math.round(total * 100) / 100;
      totalEl.textContent = fmt(total);
      barEl.style.width = Math.min(total, 100) + '%';

      var grade = gradeFor(total);
      var complete = filled === markInputs.length && !invalid;
      barEl.className = 'meter-bar bar-' + grade;

      if (complete) {
        gradeEl.textContent = grade;
        gradeEl.className = 'grade grade-lg grade-' + grade;
        noteEl.textContent = grade === 'F' ? 'Fail' : 'Pass';
      } else {
        gradeEl.textContent = filled ? grade : '–';
        gradeEl.className = 'grade grade-lg' + (filled ? ' grade-' + grade + ' grade-pending' : '');
        noteEl.textContent = invalid
          ? 'One or more marks are outside the allowed range.'
          : 'Enter all three marks to see the final grade.';
      }

      scaleRows.forEach(function (row) {
        row.classList.toggle('is-current', filled > 0 && row.dataset.scale === grade);
      });
    };

    markInputs.forEach(function (i) { i.addEventListener('input', updatePreview); });
    updatePreview();
  }

  /* ---------- Delete confirmation ---------- */
  document.querySelectorAll('form[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (ev) {
      if (!window.confirm(form.dataset.confirm)) ev.preventDefault();
    });
  });

  /* ---------- Print buttons ---------- */
  document.querySelectorAll('[data-print]').forEach(function (btn) {
    btn.addEventListener('click', function () { window.print(); });
  });
})();
