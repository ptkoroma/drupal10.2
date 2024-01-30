import Vue from "vue";
import lodash from "lodash";
import Vuex, { mapActions, mapGetters } from "vuex";
import VueObserveVisibility from 'vue-observe-visibility';
import stores from "../../storage/stores";
import UserList from "../UserList";
import GroupList from "../GroupList";
import { mapFields } from "vuex-map-fields";
import EntitySelected from "../EntitySelected";
import Extender from "../../Extender";
import { isDevMode } from "../../storage/helper";

Vue.use(VueObserveVisibility)
Vue.use(Vuex)

const store = new Vuex.Store({
  ...stores,
});

export default {
  name: 'app',
  extends: Extender,
  store,
  components: {
    UserList,
    GroupList,
    EntitySelected,
  },
  props: {
    'data-type': {
      type: String,
      default: 'id'
    },
    'data-token': {},
    "data-autocomplete-path": {},
    'data-drupal-selector':{},
    'data-multiple':{},
    'data-name':{},
    'data-id':{},
    'data-top-tabs-with-types': {},
    'data-class':{},
    'data-user-load':{},
    'data-user-list':{
      type: Object,
      default: function (){
        return {};
      }
    },
    'data-user-default':{
      type: Array,
      default: function (){
        return [];
      }
    },
  },
  data() {
    return {
      debug: isDevMode,
      active: 'users',
      filter: "",
      group: "",
    }
  },
  computed: {
    ...mapFields([
      'selectedIds',
      'selectedGroupsIds',
    ]),
    ...mapGetters([
      'getAllAllowed',
      'groupsOfEntity',
      'getAllAllowedUsers',
      'getAllAllowedClasses',
      'getAllAllowedTrainings'
    ]),
    applyFiltersToUsers: function () {
      return lodash.pickBy(this.getAllAllowedUsers, (entity) => {
        let members = entity.getMember();
        let group = this.group.id;
        return (group ? lodash.includes(members, group) : true) && (
          lodash.includes(lodash.lowerCase(entity.getName()), lodash.lowerCase(this.filter)) ||
          lodash.includes(lodash.lowerCase(entity.getMail()), lodash.lowerCase(this.filter))
        );
      });
    },
    applyFiltersToGroupClasses: function () {
      return this.applyFiltersToGroup(this.getAllAllowedClasses)
    },
    applyFiltersToGroupTrainings: function () {
      return this.applyFiltersToGroup(this.getAllAllowedTrainings)
    },

    /**
     * Because selectedIds and selectedGroupsIds are always numeric,
     * it should be converted to suported format before sending to backend.
     *
     * @example
     *   [ 4, 6 ] ==> [ "user_6", "class_4" ]
     *
     * @returns {string|int[]}
     */
    getStoringValuesByEntity: {
      get: function () {
        let dataType = (this.dataType)
        return [
          ...lodash.map(this.selectedIds, v=>this.getEntityById('user', v)[dataType]),
          ...lodash.map(this.selectedGroupsIds, v=>this.getEntityById('group', v)[dataType]),
        ]
      },
      set(){
      }
    },
  },
  mounted() {
    // Pass all properties to Vuex storage.
    this.initLoad({
      ...this.$props,
    });
    this.$on('onUserClicked', this.onUserClicked);
    this.$on('onGroupClicked', this.onGroupClicked);
  },
  filters: {
  },
  methods: {
    ...mapActions([
      'initLoad',
      'putInitialValues',
      'findByNameAndEmail',
      'findByGroup',
    ]),
    selectedIdsInput: function ($event) {
      return $event;
    },

    applyFiltersToGroup: function (group_list){
      return lodash.sortBy(lodash.pickBy(group_list, (entity)=>{
        return  lodash.includes(lodash.lowerCase(entity.getName()), lodash.toLower(this.filter))
      }), (entity => entity.getName()));
    },

    setGroupID: function (entity) {
      this.filter = '';
      this.group = (entity)
    },
    backToGroup: function () {
      this.filter = '';
      this.group = '';
    },

    onClickTab: function (tab_name) {
      this.active = tab_name
      this.group = '';
      this.filter = '';
    },

    onUserClicked: function (entity, isUserSelected) {
      Vue.set(this, 'selectedIds', lodash.xor(this.selectedIds, [entity.id]));
      if (isUserSelected) {
        Vue.set(this, 'selectedGroupsIds', lodash.difference(this.selectedGroupsIds, entity.getMember())
        );
      }
    },

    onGroupClicked: function (entity, selected) {
      let members = lodash.map(entity.members);
      if (!selected) {
        Vue.set(this, 'selectedIds', lodash.union(this.selectedIds, members));
        Vue.set(this, 'selectedGroupsIds', lodash.union(this.selectedGroupsIds, [entity.id]));
      }
      else {
        Vue.set(this, 'selectedIds', lodash.xor(this.selectedIds, members));
        let entities = lodash.pick(this.getAllAllowedUsers, members);
        let entities_members_id = lodash.concat(...(lodash.map(entities, (entity => entity.getMember()))));
        Vue.set(this, 'selectedGroupsIds',
          lodash.difference(this.selectedGroupsIds, [entity.id, ...entities_members_id])
        );
      }
    },
    getStoringIdByEntity: function (entity) {
      return entity[this.dataType];
    },
    /**
     * Allows to override tabs.
     *
     * The object key is constant and should be "user", "classes", or "trainings".
     * Only label cn be overridden for now.
     *
     * It is not completed implementation that allows to use widget on entity_reference field.
     *
     * @returns {object}
     */
    topTabsWithTypes: function () {
      return this.$props.dataTopTabsWithTypes ? JSON.parse(this.$props.dataTopTabsWithTypes) : {
        users: 'Users',
        classes: 'Classes',
        trainings: 'Trainings',
      }
    },
    topTabsWithTypeLabel: function (active) {
      return (this.topTabsWithTypes()[active] || "").toLocaleLowerCase();
    }
  }
}
