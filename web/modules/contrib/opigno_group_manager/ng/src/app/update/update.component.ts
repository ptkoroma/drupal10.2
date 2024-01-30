import { Component, OnInit, Input, Output, EventEmitter } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { DomSanitizer } from '@angular/platform-browser';
import { ActivatedRoute } from '@angular/router';

import * as globals from '../app.globals';
import { AppService } from '../app.service';
import { Entity } from '../entity/entity';

declare var jQuery: any;

@Component({
  selector: 'entity-update',
  templateUrl: './update.component.html',
  styleUrls: ['./update.component.scss']
})

export class UpdateComponent implements OnInit {
  @Input('selectedEntity') selectedEntity: Entity;
  @Input('groupId') groupId: number;
  @Input('apiBaseUrl') apiBaseUrl: number;

  @Output() closed: EventEmitter<string> = new EventEmitter();

  entityForm: any;
  mainId: any;
  getEntityFormUrl: string;
  text_property_of: string;

  constructor(
    private http: HttpClient,
    private sanitizer: DomSanitizer,
    private appService: AppService,
    private route: ActivatedRoute
  ) {
    this.getEntityFormUrl = window['appConfig'].getEntityFormUrl;
    this.text_property_of = window['appConfig'].text_property_of;
  }

  ngOnInit(): void {
    this.route.params.subscribe(params => {
      this.mainId = !isNaN(+params['id']) ? +params['id'] : '';
    });

    this.entityForm = this.sanitizer.bypassSecurityTrustResourceUrl(this.apiBaseUrl + this.appService.replaceUrlParams(this.getEntityFormUrl, { '%groupId': this.groupId, '%bundle': this.selectedEntity.contentType, '%entityId': this.selectedEntity.entityId }));
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
