'use strict';

(function($) {
  $(document).
      on('click touch', '.wpcpp-login-form-password-visible', function(e) {
        e.preventDefault();

        let $this = $(this);
        let $pw = $this.closest('.wpcpp-login-form-password-wrapper').
            find('.wpcpp-login-form-password');

        if ($this.hasClass('wpcpp-login-form-password-hide')) {
          // hide password
          $pw.attr('type', 'password');
          $this.removeClass('wpcpp-login-form-password-hide');
        } else {
          $pw.attr('type', 'text');
          $this.addClass('wpcpp-login-form-password-hide');
        }
      });
})(jQuery);