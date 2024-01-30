import { Component, OnInit, Input, Output, EventEmitter } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { DomSanitizer } from '@angular/platform-browser';
import { ActivatedRoute } from '@angular/router';

import * as globals from '../app.globals';
import { AppService } from '../app.service';
import { Entity } from '../entity/entity';

declare var jQuery: any;

@Component({
  selector: 'meetings-list',
  templateUrl: './meetings.component.html',
  styleUrls: ['./meetings.component.scss']
})

export class MeetingsListComponent implements OnInit {
  @Input('selectedEntity') selectedEntity: Entity;
  @Input('groupId') groupId: number;
  @Input('apiBaseUrl') apiBaseUrl: number;

  @Output() closed: EventEmitter<string> = new EventEmitter();

  entityForm: any;
  mainId: any;
  getEntityFormUrl: string;
  text_score: string;
  form = {
    bundle: null,
    existingEntity: null,
  };

  constructor(
    private http: HttpClient,
    private sanitizer: DomSanitizer,
    private appService: AppService,
    private route: ActivatedRoute
  ) {
    this.getEntityFormUrl = window['appConfig'].getEntityFormUrl;
    this.text_score = window['appConfig'].text_score;
  }

  ngOnInit(): void {
    this.route.params.subscribe(params => {
      this.mainId = !isNaN(+params['id']) ? +params['id'] : '';
    });

    let contentType = this.selectedEntity.contentType;
    let url = '';
    // Set path to score ILT.
    if (contentType == 'ContentTypeILT') {
      url = '/ilt/%meeting/score';
    }
    // Or set path to score Live Meeting.
    if (contentType == 'ContentTypeMeeting') {
      url = '/moxtra/meeting/%meeting/score';
    }

    const entityFormUrl = url;

    this.entityForm = this.sanitizer.bypassSecurityTrustResourceUrl(this.apiBaseUrl + this.appService.replaceUrlParams(entityFormUrl, { '%meeting': this.selectedEntity.entityId}));
    this.listenFormCallback();
  }

  update(entity: Entity): void {
    this.closed.emit(null);
  }

  close(): void {
    this.closed.emit(null);
  }

  listenFormCallback(): void {
    let that = this;

    var intervalId = setInterval(function() {
      if (typeof window['iframeFormValues'] !== 'undefined') {
        clearInterval(intervalId);

        let formValues = window['iframeFormValues'];

        that.selectedEntity.title = formValues['title'];
        that.selectedEntity.imageUrl = formValues['imageUrl'];
        that.selectedEntity.in_skills_system = formValues['in_skills_system'];
        that.selectedEntity.isMandatory = formValues['isMandatory'];

        delete window['iframeFormValues'];
        that.close();
      }
    }, 500);
  }
}
