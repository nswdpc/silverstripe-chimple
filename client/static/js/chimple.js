/**
 * Submit subscribe form via XHR to the form action
 * Uses FormData for submission of data
 *
 */
if(typeof window.FormData != 'undefined') {

  const ChimpleSubmit = function() {};
  ChimpleSubmit.prototype = {
    form: null,
    default_success: 'Your submission was successful',
    default_error: 'Your submission could not be accepted at the current time',
    client: null,
    formdata: null,
    init: function(form) {
      this.client = new XMLHttpRequest();
      this.form = form;
      return this;
    },
    handle: function() {
      var _self = this;
      this.form.addEventListener(
        'submit',
        function(e) {
          event.preventDefault();
          return _self.submitForm();
        }
      );
    },
    submitForm: function() {
      var _self = this;

      this.client.addEventListener(
        "load",
        function(event) {
          var success = this.status === 0 || (this.status >= 200 && this.status < 400);
          _self.addFormMessage(success ? 'good' : 'error');
        }
      );
      this.client.addEventListener(
        "error",
        function( event ) {
          _self.addFormMessage('error');
        }
      );

      // create the formdata
      this.formdata = new FormData( this.form );
      this.formdata.append('ajax', 1);

      // send
      this.client.open("POST", this.form.action);
      this.client.send(this.formdata);
    },

    addFormMessage : function(type) {
      var msgs = this.form.querySelectorAll('.message');
      msgs.forEach(
        function(m) {
          m.parentNode.removeChild(m);
        }
      );
      var text = this.client.getResponseHeader("X-Submission-Description");
      if(!text) {
        // fallback text
        text = (type == 'good') ? this.default_success : this.default_error;
      }
      var msg  = document.createElement('div');
      msg.className = 'message';
      msg.classList.add(type);
      msg.textContent = text;
      this.form.appendChild(msg);
    }

  };

  const chimples = document.querySelectorAll('form.form-subscribe.chimple[data-xhr]');
  chimples.forEach(
    function(f) {
      var c = new ChimpleSubmit();
      c.init(f).handle();
    }
  );

}
