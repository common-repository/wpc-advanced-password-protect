'use strict';

(function($) {
  $(function() {
    wpcpp_show_conditional();
    wpcpp_show_apply();
    wpcpp_time_picker();
    wpcpp_build_label();
    wpcpp_products_init();
    wpcpp_terms_init();
    wpcpp_password_init();
    wpcpp_roles_init();
    wpcpp_users_init();
    wpcpp_sortable();
  });

  $(document).
      on('keyup change keypress', '.wpcpp_rule_name_input', function() {
        let $this = $(this), value = $this.val();

        if (value !== '') {
          $this.closest('.wpcpp_rule').
              find('.wpcpp_rule_label_name').
              text(value);
        } else {
          $this.closest('.wpcpp_rule').
              find('.wpcpp_rule_label_name').
              text($this.data('name'));
        }
      });

  $(document).on('change', '.wpcpp_time_type', function() {
    var $time = $(this).closest('.wpcpp_time');

    wpcpp_show_conditional($time);
  });

  $(document).
      on('change',
          '.wpcpp_time select:not(.wpcpp_time_type), .wpcpp_time input:not(.wpcpp_time_val)',
          function() {
            var val = $(this).val();
            var show = $(this).
                closest('.wpcpp_time').
                find('.wpcpp_time_type').
                find(':selected').
                data('show');

            $(this).
                closest('.wpcpp_time').
                find('.wpcpp_time_val').data(show, val).
                val(val).
                trigger('change');
          });

  $(document).on('change', '.wpcpp_apply_selector', function() {
    var $action = $(this).closest('.wpcpp_rule');
    wpcpp_show_apply($action);
    wpcpp_build_label();
    wpcpp_terms_init();
    wpcpp_products_init();
  });

  $(document).on('click touch', '.wpcpp_rule_heading', function(e) {
    if (($(e.target).closest('.wpcpp_rule_duplicate').length === 0) &&
        ($(e.target).closest('.wpcpp_rule_remove').length === 0)) {
      $(this).closest('.wpcpp_rule').toggleClass('active');
    }
  });

  // search product
  $(document).on('change', '.wpcpp_products_select', function() {
    var $this = $(this);
    var val = $this.val();
    var _val = '';

    if (val !== null) {
      if (Array.isArray(val)) {
        _val = val.join();
      } else {
        _val = String(val);
      }
    }

    $this.attr('data-val', _val);
    $this.closest('.wpcpp_rule').
        find('.wpcpp_apply_val').
        val(_val).
        trigger('change');
  });

  // search terms
  $(document).on('change', '.wpcpp_terms_select', function() {
    var $this = $(this);
    var val = $this.val();
    var _val = '';
    var apply = $this.closest('.wpcpp_rule').
        find('.wpcpp_apply_selector').
        val();

    if (val !== null) {
      if (Array.isArray(val)) {
        _val = val.join();
      } else {
        _val = String(val);
      }
    }

    $this.data(apply, _val);
    $this.closest('.wpcpp_rule').
        find('.wpcpp_apply_val').
        val(_val).
        trigger('change');
  });

  $(document).on('click touch', '.wpcpp_new_rule', function(e) {
    e.preventDefault();
    $('.wpcpp_rules').addClass('wpcpp_rules_loading');

    var data = {
      action: 'wpcpp_add_rule', nonce: wpcpp_vars.nonce,
    };

    $.post(ajaxurl, data, function(response) {
      $('.wpcpp_rules').append(response);
      wpcpp_time_picker();
      wpcpp_show_conditional();
      wpcpp_show_apply();
      wpcpp_build_label();
      wpcpp_products_init();
      wpcpp_terms_init();
      wpcpp_password_init();
      wpcpp_roles_init();
      wpcpp_users_init();
      $('.wpcpp_rules').removeClass('wpcpp_rules_loading');
    });
  });

  $(document).on('click touch', '.wpcpp_rule_duplicate', function(e) {
    e.preventDefault();
    $('.wpcpp_rules').addClass('wpcpp_rules_loading');

    var $action = $(this).closest('.wpcpp_rule');
    var form_data = $action.
        find('input, select, button, textarea').
        serialize() || 0;
    var data = {
      action: 'wpcpp_add_rule', form_data: form_data, nonce: wpcpp_vars.nonce,
    };

    $.post(ajaxurl, data, function(response) {
      $(response).insertAfter($action);
      wpcpp_time_picker();
      wpcpp_show_conditional();
      wpcpp_show_apply();
      wpcpp_build_label();
      wpcpp_products_init();
      wpcpp_terms_init();
      wpcpp_password_init();
      wpcpp_roles_init();
      wpcpp_users_init();
      $('.wpcpp_rules').removeClass('wpcpp_rules_loading');
    });
  });

  $(document).on('click touch', '.wpcpp_new_time', function(e) {
    var $timer = $(this).closest('.wpcpp_rule').find('.wpcpp_timer');
    var data = {
      key: $(this).closest('.wpcpp_rule').data('key'),
      action: 'wpcpp_add_time',
      nonce: wpcpp_vars.nonce,
    };

    $.post(ajaxurl, data, function(response) {
      $timer.append(response);
      wpcpp_time_picker();
      wpcpp_show_conditional();
    });

    e.preventDefault();
  });

  $(document).on('click touch', '.wpcpp_rule_remove', function(e) {
    e.preventDefault();

    if (confirm('Are you sure?')) {
      $(this).closest('.wpcpp_rule').remove();
    }
  });

  $(document).on('click touch', '.wpcpp_expand_all', function(e) {
    e.preventDefault();

    $('.wpcpp_rule').addClass('active');
  });

  $(document).on('click touch', '.wpcpp_collapse_all', function(e) {
    e.preventDefault();

    $('.wpcpp_rule').removeClass('active');
  });

  $(document).on('click touch', '.wpcpp_time_remove', function(e) {
    e.preventDefault();

    if (confirm('Are you sure?')) {
      $(this).closest('.wpcpp_time').remove();
    }
  });

  function wpcpp_time_picker() {
    $('.wpcpp_dpk_date_time:not(.wpcpp_dpk_init)').wpcdpk({
      timepicker: true, onSelect: function(fd, d, dpk) {
        if (!d) {
          return;
        }

        var show = dpk.$el.closest('.wpcpp_time').
            find('.wpcpp_time_type').
            find(':selected').
            data('show');

        dpk.$el.closest('.wpcpp_time').
            find('.wpcpp_time_val').data(show, fd).val(fd).trigger('change');
      },
    }).addClass('wpcpp_dpk_init');

    $('.wpcpp_dpk_date:not(.wpcpp_dpk_init)').wpcdpk({
      onSelect: function(fd, d, dpk) {
        if (!d) {
          return;
        }

        var show = dpk.$el.closest('.wpcpp_time').
            find('.wpcpp_time_type').
            find(':selected').
            data('show');

        dpk.$el.closest('.wpcpp_time').
            find('.wpcpp_time_val').data(show, fd).val(fd).trigger('change');
      },
    }).addClass('wpcpp_dpk_init');

    $('.wpcpp_dpk_date_range:not(.wpcpp_dpk_init)').wpcdpk({
      range: true,
      multipleDatesSeparator: ' - ',
      onSelect: function(fd, d, dpk) {
        if (!d) {
          return;
        }

        var show = dpk.$el.closest('.wpcpp_time').
            find('.wpcpp_time_type').
            find(':selected').
            data('show');

        dpk.$el.closest('.wpcpp_time').
            find('.wpcpp_time_val').data(show, fd).val(fd).trigger('change');
      },
    }).addClass('wpcpp_dpk_init');

    $('.wpcpp_dpk_date_multi:not(.wpcpp_dpk_init)').wpcdpk({
      multipleDates: 5,
      multipleDatesSeparator: ', ',
      onSelect: function(fd, d, dpk) {
        if (!d) {
          return;
        }

        var show = dpk.$el.closest('.wpcpp_time').
            find('.wpcpp_time_type').
            find(':selected').
            data('show');

        dpk.$el.closest('.wpcpp_time').
            find('.wpcpp_time_val').data(show, fd).val(fd).trigger('change');
      },
    }).addClass('wpcpp_dpk_init');

    $('.wpcpp_dpk_time:not(.wpcpp_dpk_init)').wpcdpk({
      timepicker: true,
      onlyTimepicker: true,
      classes: 'only-time',
      onSelect: function(fd, d, dpk) {
        if (!d) {
          return;
        }

        var show = dpk.$el.closest('.wpcpp_time').
            find('.wpcpp_time_type').
            find(':selected').
            data('show');

        if (dpk.$el.hasClass('wpcpp_time_from') ||
            dpk.$el.hasClass('wpcpp_time_to')) {
          var time_range = dpk.$el.closest('.wpcpp_time').
                  find('.wpcpp_time_from').val() + ' - ' +
              dpk.$el.closest('.wpcpp_time').
                  find('.wpcpp_time_to').val();

          dpk.$el.closest('.wpcpp_time').
              find('.wpcpp_time_val').
              data(show, time_range).
              val(time_range).
              trigger('change');
        } else {
          dpk.$el.closest('.wpcpp_time').
              find('.wpcpp_time_val').data(show, fd).val(fd).trigger('change');
        }
      },
    }).addClass('wpcpp_dpk_init');
  }

  function wpcpp_terms_init() {
    $('.wpcpp_terms_select').each(function() {
      var $this = $(this);
      var apply = $this.closest('.wpcpp_rule').
          find('.wpcpp_apply_selector').
          val();
      var taxonomy = apply;

      $this.selectWoo({
        ajax: {
          url: ajaxurl, dataType: 'json', delay: 250, data: function(params) {
            return {
              q: params.term,
              action: 'wpcpp_search_term',
              taxonomy: taxonomy,
              nonce: wpcpp_vars.nonce,
            };
          }, processResults: function(data) {
            var options = [];
            if (data) {
              $.each(data, function(index, text) {
                options.push({id: text[0], text: text[1]});
              });
            }
            return {
              results: options,
            };
          }, cache: true,
        }, minimumInputLength: 1,
      });

      if (apply !== 'apply_all' && apply !== 'apply_product') {
        // for terms only
        if ($this.data(apply) !== undefined && $this.data(apply) !== '') {
          $this.val(String($this.data(apply)).split(',')).change();
        } else {
          $this.val([]).change();
        }
      }
    });
  }

  function wpcpp_users_init() {
    $('.wpcpp_users_select').each(function() {
      $(this).selectWoo({
        ajax: {
          url: ajaxurl, dataType: 'json', delay: 250, data: function(params) {
            return {
              q: params.term,
              action: 'wpcpp_search_user',
              nonce: wpcpp_vars.nonce,
            };
          }, processResults: function(data) {
            var options = [];
            if (data) {
              $.each(data, function(index, text) {
                options.push({id: text[0], text: text[1]});
              });
            }
            return {
              results: options,
            };
          }, cache: true,
        }, minimumInputLength: 1,
      });
    });
  }

  function wpcpp_password_init() {
    $('.wpcpp_password_select').selectWoo({
      tags: true,
      multiple: true,
    });
  }

  function wpcpp_roles_init() {
    $('.wpcpp_roles_select').selectWoo();
  }

  function wpcpp_products_init() {
    $('.wpcpp_apply_selector').each(function() {
      var $this = $(this);
      var $val = $this.closest('.wpcpp_rule').find('.wpcpp_apply_val');
      var products = $this.closest('.wpcpp_rule').
          find('.wpcpp_products_select').
          attr('data-val');

      if ($this.val() === 'apply_product') {
        $val.val(products).trigger('change');
      }
    });

    $(document.body).trigger('wc-enhanced-select-init');
  }

  function wpcpp_show_conditional($time) {
    if (typeof $time !== 'undefined') {
      var show = $time.find('.wpcpp_time_type').find(':selected').data('show');
      var $val = $time.find('.wpcpp_time_val');

      if ($val.data(show) !== undefined) {
        $val.val($val.data(show)).trigger('change');
      } else {
        $val.val('').trigger('change');
      }

      $time.find('.wpcpp_hide').hide();
      $time.find('.wpcpp_show_if_' + show).
          show();
    } else {
      $('.wpcpp_time').each(function() {
        var show = $(this).
            find('.wpcpp_time_type').
            find(':selected').
            data('show');
        var $val = $(this).find('.wpcpp_time_val');

        $val.data(show, $val.val());

        $(this).find('.wpcpp_hide').hide();
        $(this).find('.wpcpp_show_if_' + show).show();
      });
    }
  }

  function wpcpp_show_apply($action) {
    if (typeof $action !== 'undefined') {
      var apply = $action.find('.wpcpp_apply_selector').find(':selected').val();
      var apply_text = $action.find('.wpcpp_apply_selector').
          find(':selected').
          text();

      $action.find('.wpcpp_apply_text').text(apply_text);
      $action.find('.hide_apply').hide();
      $action.find('.show_if_' + apply).show();
      $action.find('.show_apply').show();
      $action.find('.hide_if_' + apply).hide();
    } else {
      $('.wpcpp_rule').each(function() {
        var $action = $(this);
        var apply = $action.find('.wpcpp_apply_selector').
            find(':selected').
            val();
        var apply_text = $action.find('.wpcpp_apply_selector').
            find(':selected').
            text();

        $action.find('.wpcpp_apply_text').text(apply_text);
        $action.find('.hide_apply').hide();
        $action.find('.show_if_' + apply).show();
        $action.find('.show_apply').show();
        $action.find('.hide_if_' + apply).hide();
      });
    }
  }

  function wpcpp_sortable() {
    $('.wpcpp_rules').
        sortable({
          handle: '.wpcpp_rule_move', placeholder: 'wpcpp_rule_placeholder',
        });
  }

  function wpcpp_build_label() {
    $('.wpcpp_rule').each(function() {
      var $action = $(this);

      if ($action.find('.wpcpp_apply_selector').length) {
        var apply = $action.find('.wpcpp_apply_selector option:selected').
            text();

        $action.find('.wpcpp_rule_label_apply').text(apply);
      }

      if ($action.find('.wpcpp_rule_name_input').length) {
        var name = $action.find('.wpcpp_rule_name_input').val();

        if (name !== '') {
          $action.find('.wpcpp_rule_label_name').text(name);
        }
      }
    });
  }
})(jQuery);