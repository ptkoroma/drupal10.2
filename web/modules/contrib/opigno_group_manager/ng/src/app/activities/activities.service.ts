import { Injectable } from '@angular/core';
import { Http } from '@angular/http';

import { Observable } from 'rxjs/Observable';
import { catchError, map, tap } from 'rxjs/operators';
import 'rxjs/add/operator/map';

import * as globals from '../app.globals';
import { AppService } from '../app.service';
import { Entity } from '../entity/entity';
import { Activity } from './activity/activity';

@Injectable()
export class ActivitiesService {
  apiBaseUrl: string;
  getModulesUrl: string;
  getActivitiesUrl: string;
  getRequiredActivitiesUrl: string;
  updateActivityUrl: string;
  deleteActivityUrl: string;
  getActivityTypesUrl: string;
  getActivityListUrl: string;
  addActivityUrl: string;
  activityList: any;

  constructor(private http: Http, private appService: AppService) {
    this.apiBaseUrl = window['appConfig'].apiBaseUrl;
    this.getModulesUrl = window['appConfig'].getModulesUrl;
    this.getActivitiesUrl = window['appConfig'].getActivitiesUrl;
    this.getRequiredActivitiesUrl = window['appConfig'].getRequiredActivitiesUrl;
    this.updateActivityUrl = window['appConfig'].updateActivityUrl;
    this.deleteActivityUrl = window['appConfig'].deleteActivityUrl;
    this.getActivityTypesUrl = window['appConfig'].getActivityTypesUrl;
    this.getActivityListUrl = window['appConfig'].getActivityListUrl;
    this.addActivityUrl = window['appConfig'].addActivityUrl;
  }

  getModules(groupId): Observable<Entity[]> {
    return this.http
      .get(this.apiBaseUrl + this.appService.replaceUrlParams(this.getModulesUrl, { '%groupId': groupId }))
      .map(response => response.json() as Entity[]);
  }

  getActivities(moduleId): Observable<Activity[]> {
    return this.http
      .get(this.apiBaseUrl + this.appService.replaceUrlParams(this.getActivitiesUrl, { '%moduleId': moduleId }))
      .map(response => response.json() as Activity[]);
  }

  getRequiredActivities(contentType, entityId): Observable<Activity[]> {
    return this.http
    .get(this.apiBaseUrl + this.appService.replaceUrlParams(this.getRequiredActivitiesUrl, { '%contentType': contentType, '%entityId': entityId }))
    .map(response => response.json() as Activity[]);
  }

  updateActivity(moduleId, omrId, maxScore): Observable<Activity[]> {
    if (maxScore === '') maxScore = 0;

    return this.http
      .post(this.apiBaseUrl + this.appService.replaceUrlParams(this.updateActivityUrl, { '%moduleId': moduleId }), JSON.stringify({ omr_id: omrId, max_score : maxScore }))
      .map(response => response.json() as Activity[]);
  }

  deleteActivity(moduleId, omrId): Observable<Activity[]> {
    return this.http
      .post(this.apiBaseUrl + this.appService.replaceUrlParams(this.deleteActivityUrl, { '%moduleId': moduleId }), JSON.stringify({ omr_id: omrId }))
      .map(response => response.json() as Activity[]);
  }

  getActivityTypes(): Observable<any[]> {
    return this.http
      .get(this.apiBaseUrl + this.getActivityTypesUrl)
      .map(response => response.json() as any[]);
  }

  getActivityList(moduleId): Observable<Activity[]>|any {
    let result;
    try {
      result = this.http
      .get(this.apiBaseUrl + this.appService.replaceUrlParams(this.getActivityListUrl, { '%opigno_module': moduleId}))
      .pipe(
        map(response => response.json() as Activity[])
      )
    }
    catch (e) {
      result = false;
    }

    return result;
  }

  addActivity(moduleId, activityId): Observable<any> {
    const group = this.getParams(window.location.href, 'group');
    return this.http
      .get(this.apiBaseUrl + this.appService.replaceUrlParams(this.addActivityUrl, { '%opigno_module': moduleId, '%opigno_activity': activityId, '%group_id': group }))
      .map(response => response.json() as any);
  }

  getParams(url, needed) {
    url = url.replace('http://', '');
    url = url.replace('https://', '');
    url = url.replace('www.', '');
    let params = url.split('/');

    if (needed) {
      for (let i = 0; i < params.length; i++) {
        if (params[i] === needed) {
          return params[i + 1];
        }
      }
    }
    return '';
  }

}
