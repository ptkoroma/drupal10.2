export default function (doSomeThing, ms =1000) {
  return new Promise((resolve) => {
    setTimeout( () => {
      resolve(doSomeThing());
    }, ms)
  });
}
