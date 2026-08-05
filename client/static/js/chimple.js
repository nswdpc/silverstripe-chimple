/**
 * Submit subscribe form via XHR to the form action
 * Uses FormData for submission of data
 */
if (typeof window.FormData !== 'undefined') {

  class ChimpleSubmit {
    constructor(form) {
      this.form = form;
      this.defaultSuccess = 'Your submission was successful';
      this.defaultError = 'Your submission could not be accepted at the current time';
      this.client = new XMLHttpRequest();
      this.formdata = null;
    }

    init() {
      return this;
    }

    handle() {
      console.log('handle');
      this.form.addEventListener('submit', (e) => {
        e.preventDefault();
        this.submitForm();
      });
    }

    submitForm() {
      this.client.addEventListener('load', () => {
        const ok = this.client.getResponseHeader('X-Submission-OK');
        const success = (ok === '1' && this.client.status === 200);
        this.addFormMessage(success ? 'good' : 'error');
      });

      this.client.addEventListener('error', () => {
        this.addFormMessage('error');
      });

      this.client.addEventListener('abort', () => {
        this.addFormMessage('error');
      });

      // Create the formdata
      this.formdata = new FormData(this.form);
      this.formdata.append('ajax', '1');

      // Send
      this.client.open('POST', this.form.action);
      this.client.send(this.formdata);
    }

    addFormMessage(type) {
      const msgs = this.form.querySelectorAll('.message');
      msgs.forEach(m => m.remove());

      let text = this.client.getResponseHeader('X-Submission-Description');
      if (!text) {
        text = (type === 'good') ? this.defaultSuccess : this.defaultError;
      }

      const msg = document.createElement('div');
      msg.className = `message ${type}`;
      msg.textContent = text;
      this.form.appendChild(msg);
    }
  }

  window.addEventListener('DOMContentLoaded', () => {
    const chimples = document.querySelectorAll('form.form-subscribe.chimple[data-xhr]');
    chimples.forEach(f => {
      const c = new ChimpleSubmit(f);
      c.init().handle();
    });
  });

}
