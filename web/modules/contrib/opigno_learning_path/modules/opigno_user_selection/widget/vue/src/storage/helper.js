const isDevMode = (process.env.NODE_ENV === 'development');
const url = (url, token = null) => {
  let urlObject = new URL([(isDevMode ? process.env.VUE_APP_BASE_DOMAIN : window.location.origin), url].join(''));
  if(token) {
    let params = { token };
    Object.keys(params).forEach(key => urlObject.searchParams.append(key, params[key]));
  }
  return urlObject;
}
const parseIds = (key) => {
  let ids = key.split("_");
  return {
    id: ids.pop(),
    type: ids.join("_") || 'user',
  }
}
const log = (...data) => isDevMode ? console.log(...data) : null;
export { isDevMode, url, parseIds, log };
