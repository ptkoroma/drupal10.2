<template lang="pug">

  div(
    class="user-view"
    v-if="entity.loaded"
    :class="{selected: isUserSelected}"
    @click.stop="$emit('onUserClicked', entity, isUserSelected)"
  )
    img(
      :src="entity.getAvatar()"
    )
    div.name {{ entity.getName() }}
    i.fi.fi-rr-check(
      v-if="isUserSelected"
    )

</template>

<script lang="js">
import lodash from "lodash";
import { mapState } from "vuex";

export default {
  name: 'user-view',
  props: [
    'entity'
  ],
  mounted() {
  },
  data() {
    return {
      name: 'loading',
    }
  },
  methods: {},
  computed: {
    ...mapState([
      'selectedIds',
    ]),
    isUserSelected: function () {
      let res = lodash.includes(this.selectedIds, this.entity.id);
      return res;
    }
  }
}
</script>

<style scoped lang="scss">
.user-view {
  border: 1px solid #f5f5f5;
  border-radius: 10px;
  padding: 10px;
  background-color: #f5f5f5;
  display: flex;
  align-items: center;
  margin-bottom: 10px;
  transition: all .3s ease;
  &.selected {
    background-color: #4ad3b0;
  }

  > img {
    width: 30px;
    height: 30px;
    border-radius: 50px;
    margin: 0 10px 0 0;
  }
}
</style>
