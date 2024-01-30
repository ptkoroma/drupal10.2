import UserView from "../UserView";
import lodash from "lodash";

export default {
  name: 'user-list',
  components: {
    UserView,
  },
  props: {
    group:{},
    userList:{},
    userFilter:{},
    groupedDisplay: {
      type: Boolean,
      default: false
    },
    selectedIds: {
      type: Array,
    }
  },
  data() {
    return {}
  },
  computed: {
    getFilteredUsers: function () {
      return lodash.sortBy(lodash.pickBy(this.userList,  (value, key) => {
        return this.userFilter !== false ? lodash.includes(this.userFilter,key) : true;
      }), (entity => entity.getName()));
    },
    getFilteredUsersGroupBy: function () {
      let list_entities = lodash(this.getFilteredUsers)
        .filter({loaded: true})
        .groupBy((entity) => {
        let first = entity.getName().charAt(0);
        return lodash.capitalize(first);
      }).value();
      list_entities = (lodash.reduce(lodash.sortBy(lodash.keys((list_entities))),(entity, index) => {
        return (entity[index] = lodash.sortBy(list_entities[index], (entity => entity.getName())), entity)
      }, {}))
      return list_entities;
    },
  },
  mounted() {

  },
  methods: {}
}
