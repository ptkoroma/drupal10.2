import lodash from "lodash";
import { getField, updateField } from "vuex-map-fields";
import { Entity, strtoint } from "./types/user";
import { isDevMode, parseIds, url } from './helper';

const state = {
  dataToken: '',
  selectedIds: [],
  selectedGroupsIds: [],
  dataUserList: {},
  dataAutocompletePath: '',
  dataUserDefault: [],
  // allLoadedInitialEntities: {},
  groupsOfEntity: {
    user: {},
    group: {}
  }
};
const getters = {
  getField,
  getAllAllowed: (state, { groupsOfEntity }) => {
    return lodash(state.dataUserList)
      .mapValues((value, key) => parseIds(key))
      .mapValues(({ id, type }) => {
        return lodash.get(groupsOfEntity, `[${(type == 'user' ? 'user' : 'group')}][${id}]`)
      }).value();
  },
  getAllDefault: (state, { groupsOfEntity }) => {
    return lodash(state.dataUserDefault)
      .mapValues((key) => parseIds(key))
      .mapValues(({ id, type }) => {
      return lodash.get(groupsOfEntity, `[${(type == 'user' ? 'user' : 'group')}][${id}]`)
    }).value();
  },
  groupsOfEntity: store => store.groupsOfEntity,
  getAllAllowedUsers: state => state.groupsOfEntity['user'],
  getAllAllowedGroups: state => state.groupsOfEntity['group'],
  getAllAllowedClasses: state => lodash.pickBy(state.groupsOfEntity['group'], { 'type': 'class' }),
  getAllAllowedTrainings: state => lodash.pickBy(state.groupsOfEntity['group'], { 'type': 'training' }),
};
const mutations = {
  updateField,
  setInitialGroupedByType: (store, payload) => {
    // let allLoadedInitialEntities = payload;
    let groupsOfEntity = lodash(payload)
      .groupBy(v => {
        return v.type == 'user' ? 'user' : 'group';
      })
      .mapValues((value) => {
        return lodash.mapKeys(value, (v) => v.id);
      })
      .value();
    // store.allLoadedInitialEntities = allLoadedInitialEntities;
    store.groupsOfEntity = { ...store.groupsOfEntity, ...groupsOfEntity };
  },
  setInitialValues: (store, {
    dataUserList,
    dataUserDefault,
    dataToken,
    dataAutocompletePath
  }) => {
    store.dataUserList = dataUserList;
    store.dataUserDefault = dataUserDefault;
    store.dataToken = dataToken;
    store.dataAutocompletePath = dataAutocompletePath;

  },
  setSelectedFromDefault: (store, { selectedIds, selectedGroupsIds }) => {

    store.selectedIds = lodash.map(selectedIds, strtoint);
    store.selectedGroupsIds = lodash.map(selectedGroupsIds, strtoint);
  },
  updateUsersInfo: (store, payload) => {
    lodash.forEach(lodash.values(payload), (user_info) => {
      store.groupsOfEntity['user'][user_info.id] = new Entity({
        id: user_info.id,
        key: `user_${user_info.id}`,
        type: "user",
        loaded: true,
        info: user_info
      })
    })
  },
  updateGroupInfo: (store, payload) => {
    lodash.forEach(lodash.values(payload), (group) => {
      let allowed_members = (lodash.intersection(lodash.map(store.groupsOfEntity['user'], 'id'), lodash.map(group.members)))
      state.groupsOfEntity['group'] = Object.assign({}, state.groupsOfEntity['group'], {
          [group.id]: new Entity({
            ...group,
            members: allowed_members
          }),
        }
      );
    })
  }
};
const actions = {
  initLoad: async ({ dispatch }, {
    dataUserList,
    dataUserDefault,
    dataToken,
    dataAutocompletePath
  }) => {

    await dispatch('putInitialValues', {
      dataUserDefault: dataUserDefault,
      dataUserList: dataUserList,
      dataToken: dataToken,
      dataAutocompletePath: dataAutocompletePath,
    });
    await dispatch('loadAllUsersInfo');

  },
  setSelectedFromDefault: ({ commit, getters: { getAllDefault } }) => {
    // @todo
    commit('setSelectedFromDefault', {
      selectedIds: lodash.map(lodash.pickBy(getAllDefault, { 'type': 'user' }), v => v.id),
      selectedGroupsIds: lodash.map(lodash.pickBy(getAllDefault, { 'type': 'class' }), v => v.id),
    })
  },
  loadAllUsersInfo: async ({ dispatch, commit, rootGetters, rootState:{ dataAutocompletePath} }) => {
    let uids = lodash.keys(rootGetters.getAllAllowedUsers);

    // Better to use axios here.
    // const token = await fetch(url('/session/token')).then(r=>r.text());
    let data = await fetch(url(dataAutocompletePath), {
      method: 'POST',
      body: JSON.stringify(uids),
      headers: {
        'Content-Type': 'application/json',
        // 'X-CSRF-Token': token,
      },
    }).then(response => response.json())
      .catch(() => (isDevMode ? require('../../db.json') : {}));
    commit('updateUsersInfo', data.users);
    commit('updateGroupInfo', data.members)

    dispatch('setSelectedFromDefault');
  },
  putInitialValues: ({ state, commit }, payload) => {
    commit('setInitialValues', payload)
    let groupByType = lodash(state.dataUserList)
      .mapValues((value, key) => parseIds(key))
      .mapValues(({ id, type }) => {
        return new Entity({
          id: id,
          key: `${type}_${id}`,
          type,
          loaded: false,
        })
      })
      //   .pickBy((value, key) => {
      //   return !lodash.includes(state.dataUserDefault, key);
      // })
      // .groupBy(v => {
      //   return v.type == 'user' ? 'user' : 'group';
      // })
      // .mapValues((value) => {
      //   return lodash.mapKeys(value, (v) => v.id);
      // })
      .value();
    commit('setInitialGroupedByType', groupByType)
  },
  findByNameAndEmail: (store, payload) => {
    return payload.length ? ['user_1'] : false;
  },
};
export default { state, getters, mutations, actions };
