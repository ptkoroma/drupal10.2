<template lang="pug">

  transition-group(class="entity-selected" name="mode-fade" mode="out-in")
    div.label-wrapper(
      :key="entity.key"
      v-for="(entity,index) in getFilteredUsers"
      v-if="entity.loaded"
      @click="()=>onClickEntity(entity)"
    )
      div.label
        img(
          :src="entity.getAvatar()"
        )
        div.text {{ entity.getName() }}
        div.close
          i.fi.fi-rr-cross-small

</template>

<script lang="js">
import lodash from "lodash"
import { mapGetters, mapState } from "vuex";
import UserView from "./UserView";
import GroupView from "./GroupView";
import Extender from "../Extender";

export default {
  name: 'entity-selected',
  extends: Extender,
  components: {
    UserView,
    GroupView,
  },
  props: [
    'selectedIds',
    'selectedGroupsIds'
  ],
  mounted() {
  },
  data() {
    return {}
  },
  methods: {
    getAll: function () {
      let res = [...this.selectedIds, ...this.selectedGroupsIds]
      return res;
    },
    isUser: (entity)=>(entity.type === 'user'),
    onClickEntity: function (entity) {
      this.$emit(this.isUser(entity) ? 'onUserClicked' : 'onGroupClicked', entity, true);
    }
  },
  computed: {
    ...mapState([
      'groupsOfEntity',
    ]),
    ...mapGetters([
      'getAllAllowedUsers',

    ]),
    getFilteredUsers() {
      let res = lodash(this.selectedIds)
        .map((v) => this.getEntityById('user', v))
        // @TODO
        .filter()
        .filter(entity => {
          return !lodash.intersection(this.selectedGroupsIds, entity.getMember()).length;
        })
        .value();

      let res2 = lodash(this.selectedGroupsIds)
        .map((v) => this.getEntityById('group', v))
        .filter()
        .value();
      return [...res, ...res2];
    }
  }
}
</script>

<style scoped lang="scss">
.entity-selected {
  display: flex;
  .label {
    cursor: pointer;
    display: flex;
    box-sizing: border-box;
    border: 1px solid #f5f5f5;
    border-radius: 10px;
    padding: .2em .6em;
    margin: 0em .5em .3em 0em;
    background-color: #f5f5f5;
    justify-content: center;
    align-items: center;
    > img {
      width: 10px;
      height: 10px;
      border-radius: 50px;
      margin: 0 10px 0 0;
    }
  }

  .close {
    padding: 0 0 0 .5em;
  }
}
</style>
