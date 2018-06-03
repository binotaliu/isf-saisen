require('./bootstrap');

const app = new Vue({
  el: '#app',
  data: {
    profile: {
      name: '',
      phone: '',
      email: '',
      address: '',
    },
    payment: {
      amount: 1000,
      custom_amount: 500,
      type: 'monthly',
      count: 36,
      message: '',
    },
    form_errors: [],

    submitting: false,

  },
  methods: {
    submit() {
      this.submitting = true;

      axios
        .post('_/donations', { profile: this.profile, payment: this.payment })
        .then(response => {
          window.location.href = response.data.redirect;
         })
        .catch(error => {
          notyf.alert('發生錯誤');
          if (error.response && error.response.data && error.response.data.errors) {
            this.$set(this, 'form_errors', Object.values(error.response.data.errors));
          }
          this.submitting = false;
        });
    },
  },
});
