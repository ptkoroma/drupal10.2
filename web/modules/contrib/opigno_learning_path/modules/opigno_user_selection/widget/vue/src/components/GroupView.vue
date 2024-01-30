<template lang="pug">

  section(
    class="group-view"
    v-if="entity.loaded"
    :class="{selected: isGroupSelected}"
    @click="onGroupClicked"
  )
    img(
      :src="entity.getAvatar()"
    )
    div.name {{ entity.getName() }}
    i.fi.fi-rr-users(@click.stop="setGroupID")
    i.fi.fi-rr-check(
      v-if="isGroupSelected"
    )

</template>

<script lang="js">
  import { mapState } from "vuex";
  import lodash from "lodash";

  export default  {
    name: 'group-view',
    props: [
      'entity'
    ],
    mounted() {

    },
    data() {
      return {

      }
    },
    methods: {
      onGroupClicked: function (){
        this.$emit('onGroupClicked', this.entity, this.isGroupSelected)
      },
      setGroupID: function (){
        this.$emit('viewGroup', this.entity)
      },
      isGroupKeyInSelected: function (){
        return lodash.includes(this.selectedGroupsIds, this.entity.id);
      },
      isAllUsersIdInSelected: function (){
        let res = lodash.intersection([...this.selectedIds], lodash.map(this.entity.members));
        return res.length==lodash.map(this.entity.members).length
      },
    },
    computed: {
      ...mapState([
        'selectedIds',
        'selectedGroupsIds',
      ]),
      isGroupSelected: function (){
        return this.isGroupKeyInSelected() && this.isAllUsersIdInSelected()
      }
    }
}
</script>

<style scoped lang="scss">
  .group-view {
    cursor: pointer;
    border: 1px solid #f5f5f5;
    border-radius: 10px;
    padding: 10px;
    background-color: #f5f5f5;
    display: flex;
    align-items: center;
    justify-content: space-around;
    margin-bottom: 10px;;
    transition: all .3s ease;
    &.selected{
      background-color: #4AD3B0;
    }
    .name{

      flex-basis: 100%;
    }
    > img {
      width: 30px;
      height: 30px;
      border-radius: 50px;
      margin: 0 10px 0 0;
    }
  }
</style>
