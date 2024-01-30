import { Component, OnInit, Input, Output, EventEmitter } from '@angular/core';

@Component({
  selector: 'activity',
  templateUrl: './activity.component.html',
  styleUrls: ['./activity.component.css']
})
export class ActivityComponent implements OnInit {

  @Input('module') module: any;
  @Input('activity') activity: any;
  @Output() updateActivityEvent = new EventEmitter();
  @Output() showDeleteEvent = new EventEmitter();

  text_move: string;
  text_preview: string;
  text_max_score: string;
  text_edit: string;
  text_delete: string;

  constructor() {
    this.text_move = window['appConfig'].text_move;
    this.text_preview = window['appConfig'].text_preview;
    this.text_max_score = window['appConfig'].text_max_score;
    this.text_edit = window['appConfig'].text_edit;
    this.text_delete = window['appConfig'].text_delete;
  }

  ngOnInit() { }

  updateActivity(e) {
    this.updateActivityEvent.emit(this.activity);
  }

  showDelete() {
    this.showDeleteEvent.emit(this.activity);
  }



}
