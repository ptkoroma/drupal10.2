import { Injectable } from '@angular/core';

import * as globals from './app.globals';
import {Subject} from "rxjs";

@Injectable()
export class AppService {

  linksStatus = true;

  // Observable sources
  private needUpdateSources = new Subject<boolean>();
  // Observable streams
  needUpdate$ = this.needUpdateSources.asObservable();


  /** Workaround used for 1..n loops */
  range(value) {
    let a = [];

    for (let i = 0; i < value; ++i) {
      a.push(i + 1)
    }

    return a;
  }

  updateLinks(update: boolean) {
    this.needUpdateSources.next(update);
    this.linksStatus = update;
  }

  /** replace parameters in constants url */
  replaceUrlParams(url: string, params: object) {
    Object.keys(params).map(function(param, index) {
      let value = params[param];
      url = url.replace(new RegExp(param, 'g'), value);
    });
    return url;
  }
}
