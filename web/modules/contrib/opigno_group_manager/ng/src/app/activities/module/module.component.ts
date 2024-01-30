import { Component, OnInit, OnChanges, Input, Output, EventEmitter, ViewChild } from '@angular/core';
import { DragulaService } from 'ng2-dragula';

import { ActivitiesService } from '../activities.service';
import { Activity } from '../activity/activity';
import { ModuleService } from './module.service';
import { AddActivityComponent } from '../add/add.component';

import { Observable } from 'rxjs/Observable';
import 'rxjs/add/observable/forkJoin';

@Component({
  selector: 'module',
  templateUrl: './module.component.html',
  styleUrls: ['./module.component.css']
})
export class ModuleComponent implements OnInit {

  @ViewChild(AddActivityComponent) addEl: AddActivityComponent;
  @Output() updateEvent = new EventEmitter();
  @Output() updateActivityEvent = new EventEmitter();
  @Input('module') module: any;

  activities: Activity[];
  showDeleteModal: boolean;
  showAddModal: boolean;
  seletedActivity: Activity;
  dragging = false;
  text_activity_id_table: string;
  text_max_score_table: string;
  text_edit_table: string;
  text_remove_table: string;
  text_confirm_delete: string;
  text_cancel: string;
  text_confirm: string;

  constructor(
    private activityService: ActivitiesService,
    private moduleService: ModuleService,
    private dragulaService: DragulaService
  ) {
    this.text_activity_id_table = window['appConfig'].text_activity_id_table;
    this.text_max_score_table = window['appConfig'].text_max_score_table;
    this.text_edit_table = window['appConfig'].text_edit_table;
    this.text_remove_table = window['appConfig'].text_remove_table;
    this.text_confirm_delete = window['appConfig'].text_confirm_delete;
    this.text_cancel = window['appConfig'].text_cancel;
    this.text_confirm = window['appConfig'].text_confirm;

    try {
      dragulaService.setOptions('nested-bag', {
        revertOnSpill: true,
        moves: function(el, source, handle, sibling) {
          return handle.classList.contains('handle');
        },
      });
    } catch (e) { }

    dragulaService.drop.subscribe((args: any) => {
      const [bagName, elSource, bagTarget, bagSource, elTarget] = args;
      let that = this;
      setTimeout(function() {
        let orders = that.getActivitiesOrder(bagTarget);
        if (orders) {
          that.moduleService.setPositioning(orders);
        }
      }, 200);
    });
  }

  getActivitiesOrder(el: HTMLElement) {
    if (!el) {
      return;
    }

    let els = el.children;
    let activitiesOrders = [];
    let weight = -1000;

    for (var i = 0; i < els.length; i++) {
      activitiesOrders.push({
        'weight': weight,
        'omr_id': els[i]['attributes']['data-omr-id'].value
      });
      weight++;
    }

    return activitiesOrders;
  }

  ngOnInit() {
    this.setActivities();
    if (typeof this.module.editable === 'undefined') {
      this.module.editable = true;
    }
  }

  updateActivities(module) {
    this.updateEvent.emit(module);
    this.setActivities();
    if (typeof module !== 'undefined') {
      module.treeViewOpened = false;
      setTimeout(() => {
        module.treeViewOpened = true;
      })
    }
  }

  setActivities() {
    let activities = this.activityService.getActivities(this.module.entity_id);

    Observable.forkJoin([activities]).subscribe(results => {
      let activities = Object.keys(results[0]).map(function(key) { return results[0][key] });

      // Order by weight
      activities.sort(function(a, b) {
        return a.weight - b.weight;
      });

      this.activities = activities;
    });
  }

  showAdd(module) {
    const that = this
    that.showAddModal = true
    setTimeout(() => {
      if (that.addEl) {
        that.addEl.module = module
      }
    })
  }

  showDelete(activity) {
    this.showDeleteModal = true
    this.seletedActivity = activity
  }

  closeDelete() {
    this.showDeleteModal = false
  }

  deleteActivity() {
    if (!this.seletedActivity) {
      return
    }

    let that = this
    let activity = this.activityService.deleteActivity(that.module.entity_id, that.seletedActivity.omr_id)

    Observable.forkJoin([activity]).subscribe(results => {
      that.activities.forEach(function(a, index) {
        if (a == that.seletedActivity) {
          that.activities.splice(index, 1)
        }
      })
      that.seletedActivity = null
    })

    that.closeDelete()
  }

  updateActivity(activity) {
    if (!activity) {
      return;
    }

    let that = this;
    this.updateActivityEvent.emit({activity, that});
  }

}
