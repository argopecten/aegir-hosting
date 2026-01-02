(function (Drupal, drupalSettings) {
  function getAliasValues(wrapper) {
    var inputs = wrapper.querySelectorAll('#hosting-aliases input');
    var values = [];

    inputs.forEach(function (input) {
      var value = input.value.trim();
      if (value && values.indexOf(value) === -1) {
        values.push(value);
      }
    });

    return values;
  }

  function refreshRedirectionOptions(wrapper) {
    var select = wrapper.querySelector('select[name="redirection"]');
    if (!select) {
      return;
    }

    var baseOptions =
      drupalSettings.hosting_alias &&
      drupalSettings.hosting_alias.redirectionBaseOptions
        ? drupalSettings.hosting_alias.redirectionBaseOptions
        : null;
    var aliasValues = getAliasValues(wrapper);
    var selectedValue = select.value;
    var options = new Map();

    if (baseOptions) {
      Object.keys(baseOptions).forEach(function (value) {
        options.set(String(value), baseOptions[value]);
      });
    } else {
      Array.prototype.forEach.call(select.options, function (option) {
        options.set(option.value, option.text);
      });
    }

    aliasValues.forEach(function (value) {
      if (!options.has(value)) {
        options.set(value, value);
      }
    });

    while (select.firstChild) {
      select.removeChild(select.firstChild);
    }

    options.forEach(function (label, value) {
      var option = document.createElement('option');
      option.value = value;
      option.textContent = label;
      select.appendChild(option);
    });

    if (options.has(selectedValue)) {
      select.value = selectedValue;
    } else if (options.has('0')) {
      select.value = '0';
    } else if (select.options.length) {
      select.selectedIndex = 0;
    }
  }

  Drupal.behaviors.hostingAliasForm = {
    attach: function (context) {
      var wrappers = context.querySelectorAll('#hosting-aliases-wrapper');

      wrappers.forEach(function (wrapper) {
        if (wrapper.dataset.hostingAliasProcessed) {
          return;
        }
        wrapper.dataset.hostingAliasProcessed = '1';

        var aliasContainer = wrapper.querySelector('#hosting-aliases');
        if (!aliasContainer) {
          return;
        }

        aliasContainer.addEventListener('input', function (event) {
          if (event.target && event.target.tagName === 'INPUT') {
            refreshRedirectionOptions(wrapper);
          }
        });

        aliasContainer.addEventListener('change', function (event) {
          if (event.target && event.target.tagName === 'INPUT') {
            refreshRedirectionOptions(wrapper);
          }
        });

        refreshRedirectionOptions(wrapper);
      });
    }
  };
})(Drupal, drupalSettings);
