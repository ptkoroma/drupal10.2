import Vue from 'vue'
import App from './components/App'
// import VueObserveVisibility from 'vue-observe-visibility';

Vue.config.productionTip = true

// Vue.use(VueObserveVisibility)
new Vue({
  render: h => h(App, {
    props: {
      "data-data-drupal-selector": "edit-users-to-send",
      "data-class": "form-autocomplete form-select",
      "data-data-autocomplete-path": "/user_selection/users",
      "data-multiple": "multiple",
      "data-name": "users_to_send[]",
      "data-id": "edit-users-to-send",
      "data-data-user-load": "",
      "data-type": "id",
      "data-autocomplete-path": "/user_selection/entity",
      "data-top-tabs-with-types": "{\"users\":\"Users\",\"classes\":\"Classes\",\"trainings\":\"Trainings\"}",
      // "data-token": "u0QlR8jqaL65XO6RUR1SF71NiwyfQip6Cd_CYcxQW78",
      "data-user-list": require('../db.json')['users'],
      "data-user-default": ["2"]
    }
  })
}).$mount('#app')
