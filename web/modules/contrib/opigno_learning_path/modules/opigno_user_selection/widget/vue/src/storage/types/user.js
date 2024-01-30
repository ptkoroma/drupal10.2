import lodash from "lodash";

export function strtoint(str) {
  return parseInt(str) || str;
}

export class Entity {
  constructor(values) {
    Object.assign(this, values, {
      id: strtoint(values.id)
    })
  }

  getName() {
    return lodash.get(this, 'info.name', 'name')
  }

  getMember() {
    return lodash.map(lodash.get(this, 'info.member', []))
  }

  getAvatar() {
    return lodash.get(this, 'info.avatar', 'avatar')
  }

  getMail() {
    return lodash.get(this, 'info.email', 'email')
  }

  isLoaded() {
    return lodash.get(this, 'loaded', 'loaded')
  }

  getDisplayName() {
    return lodash.get(this, 'info.name', 'name')
  }
}
