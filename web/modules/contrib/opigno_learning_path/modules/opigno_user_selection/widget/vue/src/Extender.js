export default {
  methods: {
    /**
     *
     * @param string type
     * @param int entity_id
     * @returns Entity
     */
    getEntityById: function (type, entity_id) {
      return this.groupsOfEntity[type][entity_id];
    },
  }
}
