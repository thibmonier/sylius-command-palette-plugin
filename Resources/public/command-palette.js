/*
 * Command palette for the Sylius admin (tmonier/sylius-command-palette-plugin).
 * Self-contained, no build step. Every value coming from the server or the user is rendered
 * with textContent; icons are cloned from <template> elements rendered by Twig.
 */
(function () {
  'use strict';

  var root = document.querySelector('[data-tmonier-command-palette]');
  if (!root || root.dataset.tmonierCpReady) {
    return;
  }
  root.dataset.tmonierCpReady = '1';

  var DEBOUNCE_MS = 200;
  var OPTION_ID_PREFIX = 'tmonier-cp-option-';

  function parseJson(value, fallback) {
    try {
      var parsed = JSON.parse(value || '');
      return parsed && typeof parsed === 'object' ? parsed : fallback;
    } catch (e) {
      return fallback;
    }
  }

  var config = parseJson(root.dataset.config, {});
  var messages = parseJson(root.dataset.messages, {});
  var searchUrl = root.dataset.searchUrl || '';
  var minLength = Number(config.minLength) || 2;
  var maxLength = Number(config.maxLength) || 100;
  var limit = Number(config.limit) || 5;
  var pages = Array.isArray(config.pages) ? config.pages : [];
  var actions = Array.isArray(config.actions) ? config.actions : [];

  var dialog = root.querySelector('[role="dialog"]');
  var input = root.querySelector('[data-tmonier-cp-input]');
  var listbox = root.querySelector('[data-tmonier-cp-listbox]');
  var message = root.querySelector('[data-tmonier-cp-message]');
  var status = root.querySelector('[data-tmonier-cp-status]');
  var loadingIndicator = root.querySelector('[data-tmonier-cp-loading]');
  var iconTemplates = root.querySelector('[data-tmonier-cp-icons]');

  var isMac = /mac|iphone|ipad|ipod/i.test(
    (navigator.userAgentData && navigator.userAgentData.platform) || navigator.platform || ''
  );

  var state = {
    open: false,
    query: '',
    localGroups: [],
    remoteGroups: [],
    remoteQuery: '',
    loading: false,
    error: null,
    options: [],
    activeIndex: -1,
    controller: null,
    timer: null,
    previousFocus: null
  };

  // ------------------------------------------------------------------ helpers

  function translate(key, parameters) {
    var text = String(messages[key] || '');
    Object.keys(parameters || {}).forEach(function (name) {
      text = text.split('%' + name + '%').join(String(parameters[name]));
    });
    return text;
  }

  function normalize(value) {
    var text = String(value || '').toLowerCase();
    if (text.normalize) {
      text = text.normalize('NFD').replace(/[̀-ͯ]/g, '');
    }
    return text.replace(/\s+/g, ' ').trim();
  }

  function cleanQuery(value) {
    return String(value || '').replace(/\s+/g, ' ').trim().slice(0, maxLength);
  }

  function isEditable(element) {
    if (!element || element === document.body) {
      return false;
    }
    if (element.isContentEditable) {
      return true;
    }
    var tag = element.tagName;
    return tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT';
  }

  function createIcon(name) {
    if (!iconTemplates) {
      return null;
    }
    var templates = iconTemplates.querySelectorAll('template[data-icon]');
    var fallback = null;
    for (var i = 0; i < templates.length; i++) {
      if (templates[i].getAttribute('data-icon') === name) {
        return templates[i].content.cloneNode(true);
      }
      if (templates[i].getAttribute('data-icon') === 'tabler:arrow-right') {
        fallback = templates[i];
      }
    }
    return fallback ? fallback.content.cloneNode(true) : null;
  }

  // ----------------------------------------------------------- local filtering

  function filterLocal(items, query) {
    var words = normalize(query).split(' ').filter(Boolean);
    if (words.length === 0) {
      return [];
    }

    var scored = [];
    items.forEach(function (item, index) {
      var title = normalize(item.title);
      var haystack = title + ' ' + normalize(item.subtitle);
      var score = 0;
      for (var i = 0; i < words.length; i++) {
        if (haystack.indexOf(words[i]) === -1) {
          return;
        }
        // Title prefix > word of the title > anywhere in the title > section only.
        if (title.indexOf(words[i]) === 0) {
          score += 4;
        } else if (title.indexOf(' ' + words[i]) !== -1) {
          score += 3;
        } else if (title.indexOf(words[i]) !== -1) {
          score += 2;
        } else {
          score += 1;
        }
      }
      scored.push({ item: item, score: score, index: index });
    });

    scored.sort(function (a, b) {
      return b.score - a.score || a.index - b.index;
    });

    return scored.slice(0, limit).map(function (entry) {
      return entry.item;
    });
  }

  function computeLocalGroups(query) {
    var groups = [];
    var matchingPages = filterLocal(pages, query);
    if (matchingPages.length > 0) {
      groups.push({ key: 'pages', label: translate('pages'), results: matchingPages });
    }
    var matchingActions = filterLocal(actions, query);
    if (matchingActions.length > 0) {
      groups.push({ key: 'actions', label: translate('actions'), results: matchingActions });
    }
    return groups;
  }

  // ------------------------------------------------------------ remote search

  function cancelRemote() {
    if (state.timer) {
      window.clearTimeout(state.timer);
      state.timer = null;
    }
    if (state.controller) {
      state.controller.abort();
      state.controller = null;
    }
    state.loading = false;
  }

  function scheduleRemote(query) {
    cancelRemote();
    if (!searchUrl || query.length < minLength) {
      state.remoteGroups = [];
      state.remoteQuery = '';
      state.error = null;
      return;
    }

    state.loading = true;
    state.timer = window.setTimeout(function () {
      state.timer = null;
      fetchRemote(query);
    }, DEBOUNCE_MS);
  }

  function fetchRemote(query) {
    var controller = typeof AbortController === 'function' ? new AbortController() : null;
    state.controller = controller;

    var url = new URL(searchUrl, window.location.href);
    url.searchParams.set('q', query);

    window.fetch(url.toString(), {
      method: 'GET',
      credentials: 'same-origin',
      headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
      signal: controller ? controller.signal : undefined
    }).then(function (response) {
      var type = response.headers.get('Content-Type') || '';
      if (response.status === 401 || response.status === 403 || response.redirected || type.indexOf('json') === -1) {
        throw new Error('session');
      }
      if (!response.ok) {
        throw new Error('http');
      }
      return response.json();
    }).then(function (data) {
      if (state.controller !== controller) {
        return;
      }
      state.controller = null;
      state.loading = false;
      state.error = null;
      state.remoteQuery = query;
      state.remoteGroups = data && Array.isArray(data.groups) ? data.groups : [];
      render();
    }).catch(function (error) {
      if (error && error.name === 'AbortError') {
        return;
      }
      if (state.controller !== controller) {
        return;
      }
      state.controller = null;
      state.loading = false;
      state.error = error && error.message === 'session' ? 'sessionExpired' : 'error';
      state.remoteGroups = [];
      render();
    });
  }

  // ------------------------------------------------------------------ render

  function buildOption(result, index) {
    var option = document.createElement('a');
    option.className = 'tmonier-cp__option';
    option.id = OPTION_ID_PREFIX + index;
    option.setAttribute('role', 'option');
    option.setAttribute('aria-selected', 'false');
    option.setAttribute('tabindex', '-1');
    option.href = String(result.url || '#');
    option.dataset.index = String(index);

    var icon = document.createElement('span');
    icon.className = 'tmonier-cp__option-icon';
    icon.setAttribute('aria-hidden', 'true');
    var svg = createIcon(result.icon);
    if (svg) {
      icon.appendChild(svg);
    }
    option.appendChild(icon);

    var text = document.createElement('span');
    text.className = 'tmonier-cp__option-text';
    var title = document.createElement('span');
    title.className = 'tmonier-cp__option-title';
    title.textContent = String(result.title || '');
    text.appendChild(title);
    if (result.subtitle) {
      var subtitle = document.createElement('span');
      subtitle.className = 'tmonier-cp__option-subtitle';
      subtitle.textContent = String(result.subtitle);
      text.appendChild(subtitle);
    }
    option.appendChild(text);

    if (result.badge) {
      var badge = document.createElement('span');
      badge.className = 'badge tmonier-cp__option-badge';
      badge.textContent = String(result.badge);
      option.appendChild(badge);
    }

    return option;
  }

  function render() {
    var groups = state.localGroups.slice();
    // Remote results are only shown for the query they answer (or while the next one loads).
    if (state.query.length >= minLength) {
      groups = groups.concat(state.remoteGroups);
    }

    var fragment = document.createDocumentFragment();
    var options = [];

    groups.forEach(function (group, groupIndex) {
      var results = Array.isArray(group.results) ? group.results : [];
      if (results.length === 0) {
        return;
      }

      var section = document.createElement('div');
      section.className = 'tmonier-cp__group';
      section.setAttribute('role', 'group');
      section.dataset.group = String(group.key || '');

      var header = document.createElement('div');
      header.className = 'tmonier-cp__group-label';
      header.id = 'tmonier-cp-group-' + groupIndex;
      header.setAttribute('role', 'presentation');
      header.textContent = String(group.label || group.key || '');
      section.setAttribute('aria-labelledby', header.id);
      section.appendChild(header);

      results.forEach(function (result) {
        var option = buildOption(result, options.length);
        options.push({ element: option, url: option.href });
        section.appendChild(option);
      });

      fragment.appendChild(section);
    });

    listbox.replaceChildren(fragment);
    state.options = options;

    var hasResults = options.length > 0;
    input.setAttribute('aria-expanded', hasResults ? 'true' : 'false');
    loadingIndicator.hidden = !state.loading;
    root.classList.toggle('tmonier-cp--loading', state.loading);

    var text = '';
    if (!hasResults) {
      if (state.query === '') {
        text = translate('empty');
      } else if (state.loading) {
        text = translate('loading');
      } else if (state.error) {
        text = translate(state.error);
      } else {
        text = translate('noResults', { query: state.query });
      }
    } else if (state.error) {
      text = translate(state.error);
    }
    message.textContent = text;
    message.hidden = text === '';

    status.textContent = state.loading ? '' : (hasResults ? translate('results', { count: options.length }) : text);

    setActive(hasResults ? 0 : -1);
  }

  function setActive(index) {
    if (state.activeIndex >= 0 && state.options[state.activeIndex]) {
      state.options[state.activeIndex].element.setAttribute('aria-selected', 'false');
      state.options[state.activeIndex].element.classList.remove('active');
    }

    state.activeIndex = index;
    if (index < 0 || !state.options[index]) {
      input.removeAttribute('aria-activedescendant');
      return;
    }

    var element = state.options[index].element;
    element.setAttribute('aria-selected', 'true');
    element.classList.add('active');
    input.setAttribute('aria-activedescendant', element.id);
    if (element.scrollIntoView) {
      element.scrollIntoView({ block: 'nearest' });
    }
  }

  function move(delta) {
    var count = state.options.length;
    if (count === 0) {
      return;
    }
    setActive((state.activeIndex + delta + count) % count);
  }

  function openOption(index, newTab) {
    var option = state.options[index];
    if (!option) {
      return;
    }
    if (newTab) {
      window.open(option.url, '_blank', 'noopener');
      return;
    }
    close(false);
    window.location.assign(option.url);
  }

  // ------------------------------------------------------------ open / close

  function update() {
    state.query = cleanQuery(input.value);
    state.localGroups = computeLocalGroups(state.query);
    if (state.query !== state.remoteQuery) {
      if (state.query.length < minLength) {
        state.remoteGroups = [];
      }
      scheduleRemote(state.query);
    }
    render();
  }

  function open() {
    if (state.open) {
      input.focus();
      input.select();
      return;
    }
    state.open = true;
    state.previousFocus = document.activeElement;
    root.hidden = false;
    document.documentElement.classList.add('tmonier-cp-open');
    input.focus();
    input.select();
    update();
  }

  function close(restoreFocus) {
    if (!state.open) {
      return;
    }
    state.open = false;
    cancelRemote();
    root.hidden = true;
    document.documentElement.classList.remove('tmonier-cp-open');
    if (restoreFocus !== false && state.previousFocus && typeof state.previousFocus.focus === 'function') {
      state.previousFocus.focus();
    }
    state.previousFocus = null;
  }

  function trapFocus(event) {
    var focusables = Array.prototype.filter.call(
      dialog.querySelectorAll('input, button, [href]:not([tabindex="-1"]), [tabindex]:not([tabindex="-1"])'),
      function (element) {
        return !element.disabled && element.offsetParent !== null;
      }
    );
    if (focusables.length === 0) {
      event.preventDefault();
      return;
    }
    var first = focusables[0];
    var last = focusables[focusables.length - 1];
    if (event.shiftKey && document.activeElement === first) {
      event.preventDefault();
      last.focus();
    } else if (!event.shiftKey && document.activeElement === last) {
      event.preventDefault();
      first.focus();
    } else if (!dialog.contains(document.activeElement)) {
      event.preventDefault();
      first.focus();
    }
  }

  // ------------------------------------------------------------------ events

  document.addEventListener('keydown', function (event) {
    var key = event.key || '';

    if ((event.ctrlKey || event.metaKey) && !event.altKey && key.toLowerCase() === 'k') {
      event.preventDefault();
      if (state.open) {
        close();
      } else {
        open();
      }
      return;
    }

    if (!state.open) {
      if (key === '/' && !event.ctrlKey && !event.metaKey && !event.altKey && !isEditable(event.target)) {
        event.preventDefault();
        open();
      }
      return;
    }

    switch (key) {
      case 'Escape':
        event.preventDefault();
        event.stopPropagation();
        close();
        break;
      case 'ArrowDown':
        event.preventDefault();
        move(1);
        break;
      case 'ArrowUp':
        event.preventDefault();
        move(-1);
        break;
      case 'Enter':
        if (event.target === input && state.activeIndex >= 0) {
          event.preventDefault();
          openOption(state.activeIndex, event.ctrlKey || event.metaKey);
        }
        break;
      case 'Tab':
        trapFocus(event);
        break;
    }
  }, true);

  input.addEventListener('input', update);

  root.addEventListener('click', function (event) {
    var target = event.target instanceof Element ? event.target : null;
    if (!target) {
      return;
    }
    if (target.closest('[data-tmonier-cp-close]')) {
      close();
      return;
    }
    var option = target.closest('.tmonier-cp__option');
    if (option && !event.ctrlKey && !event.metaKey && !event.shiftKey && event.button === 0) {
      // Plain click: let the link navigate, just close the palette.
      close(false);
    }
  });

  listbox.addEventListener('mousemove', function (event) {
    var option = event.target instanceof Element ? event.target.closest('.tmonier-cp__option') : null;
    if (option && Number(option.dataset.index) !== state.activeIndex) {
      setActive(Number(option.dataset.index));
    }
  });

  document.querySelectorAll('[data-tmonier-command-palette-trigger]').forEach(function (trigger) {
    trigger.addEventListener('click', function (event) {
      event.preventDefault();
      open();
    });
  });

  var shortcut = isMac ? '⌘ K' : 'Ctrl K';
  document.querySelectorAll('[data-tmonier-command-palette-shortcut]').forEach(function (element) {
    element.textContent = shortcut;
  });
  root.querySelectorAll('[data-tmonier-cp-modifier]').forEach(function (element) {
    element.textContent = isMac ? '⌘' : 'Ctrl';
  });

  // Back/forward cache: never come back to a page with the palette open.
  window.addEventListener('pageshow', function () {
    close(false);
  });
})();
