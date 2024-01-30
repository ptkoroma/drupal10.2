import { Component, OnInit, Input, Output, EventEmitter } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { DomSanitizer } from '@angular/platform-browser';

import * as globals from '../../app.globals';
import { AppService } from '../../app.service';
import {subscriptionLogsToBeFn} from "rxjs/testing/TestScheduler";

@Component({
  selector: 'activities-bank',
  templateUrl: './activities-bank.component.html',
  styleUrls: ['./activities-bank.component.scss']
})

export class AddActivitiesBankComponent implements OnInit {

  @Input('module') module: any;
  @Output() updateEvent = new EventEmitter();
  @Output() closeEvent = new EventEmitter();

  activitiesBank: any;
  apiBaseUrl: string;
  addActivitiesBankUrl: string;
  text_add_activity: string;
  text_add_activities_to_the_module: string;
  text_close: string;

  constructor(
      private http: HttpClient,
      private sanitizer: DomSanitizer,
      private appService: AppService
  ) {
    this.apiBaseUrl = window['appConfig'].apiBaseUrl;
    this.addActivitiesBankUrl = window['appConfig'].addActivitiesBankUrl;
    this.text_add_activity  = window['appConfig'].text_add_activity;
    this.text_add_activities_to_the_module = window['appConfig'].text_add_activities_to_the_module;
    this.text_close = window['appConfig'].text_close;
  }

  ngOnInit() {
    this.activitiesBank = this.sanitizer.bypassSecurityTrustResourceUrl(this.apiBaseUrl + this.appService.replaceUrlParams(this.addActivitiesBankUrl, { '%opigno_module': this.module.entity_id }));
    this.listenFormCallback();
  }

  listenFormCallback(): void {
    const that = this;
    var intervalId = setInterval(function() {
      if (typeof window['closeActivityBankPanel'] !== 'undefined') {
        clearInterval(intervalId);
        delete window['closeActivityBankPanel'];
        that.updateEvent.emit(this.module);
        that.close();
      }
    }, 500);
  }

  close() {
    this.closeEvent.emit();
  }

}
