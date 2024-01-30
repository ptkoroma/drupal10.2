import { Component, OnInit, Input, ViewChild } from '@angular/core';
import { ActivatedRoute } from '@angular/router';
import { DomSanitizer } from '@angular/platform-browser';

import { Observable } from 'rxjs/Observable';
import 'rxjs/add/observable/forkJoin';

import { EntityService } from '../entity/entity.service';
import { Entity } from '../entity/entity';
import { LevelComponent } from '../level/level.component';

@Component({
  selector: 'app-index',
  templateUrl: './index.component.html',
  styleUrls: ['./index.component.css']
})
export class IndexComponent implements OnInit {

  @ViewChild(LevelComponent) moduleEl: LevelComponent;

  groupId: number;
  nextLink: any;
  viewType: string;
  entities: Entity[];
  hasNextLink = false;
  moduleContext: boolean;
  text_module: string;
  text_modules: string;
  text_tree_view: string;
  text_score: string;
  course_score_validation_error_prefix: string;
  score_validation_error_suffix: string;

  constructor(
    private route: ActivatedRoute,
    private entityService: EntityService,
    private sanitizer: DomSanitizer,
  ) {
    this.groupId = window['appConfig'].groupId;
    this.viewType = window['appConfig'].viewType;
    this.moduleContext = window['appConfig'].moduleContext;
    this.nextLink = this.sanitizer.bypassSecurityTrustHtml(window['appConfig'].nextLink);
    this.text_module = window['appConfig'].text_module;
    this.text_modules = window['appConfig'].text_modules;
    this.text_tree_view = window['appConfig'].text_tree_view;
    this.text_score = window['appConfig'].text_score;
    this.course_score_validation_error_prefix = window['appConfig'].course_score_validation_error_prefix;
    this.score_validation_error_suffix = window['appConfig'].score_validation_error_suffix;
  }

  ngOnInit(): void {
    if (this.viewType == 'modules') {
      let entities = this.entityService.getEntities(this.groupId);
      let entitiesPositions = this.entityService.getEntitiesPositions(this.groupId);

      Observable.forkJoin([entities, entitiesPositions]).subscribe(results => {
        this.entities = results[0];
        this.updateNextLink(this.entities);
      });
    }


  }

  changeScoreMini({entity, that}) {
    let sum = 0;

    for (const module of that.entities) {
      sum += +module.successScoreMin;
    }

    Object.values(this.entities).find(course => {
      if (+course.entityId === +entity.groupId) {
        return course.showError = sum === 0 && course.successScoreMin > sum;
      }
    });
  }

  addModule(entity) {
    let that = this;
    entity.treeViewOpened = true;

    setTimeout(function() {
      that.moduleEl.openAddPanel(null);
    });
  }

  updateNextLink(entities) {
    this.hasNextLink = true;

    if (this.viewType == 'manager') {
      if (!entities.length) {
        this.hasNextLink = false;
      }

      const mandatories = entities.filter(entity => entity.isMandatory == 1);

      if (!mandatories.length) {
        this.hasNextLink = false;
      }
    }
    else if (this.viewType == 'modules') {
      if (!this.entities.length) {
        this.hasNextLink = false;
      }

      const empties = this.entities.filter(entity => entity.modules_count === 0);

      if (empties.length) {
        this.hasNextLink = false;
      }
    }
    else if (this.viewType == 'activities' && !this.moduleContext) {
      if (!entities.length) {
        this.hasNextLink = false;
      }

      const empties = entities.filter(entity => entity.activity_count === 0);

      if (empties.length) {
        this.hasNextLink = false;
      }
    }
  }
}
