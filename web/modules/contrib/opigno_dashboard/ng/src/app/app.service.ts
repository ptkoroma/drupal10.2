import { Injectable } from '@angular/core';
import { Http, Headers} from '@angular/http';
import { DomSanitizer } from '@angular/platform-browser';
import { SecurityContext } from '@angular/core';

import 'rxjs/add/operator/map';
import { Observable } from 'rxjs/Observable';
import { BehaviorSubject } from 'rxjs/BehaviorSubject';

@Injectable()
export class AppService {

  private _columns = new BehaviorSubject<number>(0);
  manageDashboardAccess: boolean;
  managePanel = false;
  positions = [[], [], [], []];
  blocksContents: [string];
  apiBaseUrl: string;
  apiRouteName: string;
  getPositioningUrl: string;
  getDefaultPositioningUrl: string;
  setPositioningUrl: string;
  setDefaultPositioningUrl: string;
  restoreToDefaultAllUrl: string;
  getBlocksContentUrl: string;
  defaultConfig: any;
  defaultColumns: number;
  socialFeedMobile: any;
  loading: boolean;

  constructor(
    private http: Http,
    private sanitizer: DomSanitizer
  ) {
    this.apiBaseUrl = window['appConfig'].apiBaseUrl;
    this.apiRouteName = window['appConfig'].apiRouteName;
    this.getPositioningUrl = window['appConfig'].getPositioningUrl;
    this.getDefaultPositioningUrl = window['appConfig'].getDefaultPositioningUrl;
    this.setPositioningUrl = window['appConfig'].setPositioningUrl;
    this.setDefaultPositioningUrl = window['appConfig'].setDefaultPositioningUrl;
    this.restoreToDefaultAllUrl = window['appConfig'].restoreToDefaultAllUrl;
    this.getBlocksContentUrl = window['appConfig'].getBlocksContentUrl;
    this.defaultConfig = JSON.parse(window['appConfig'].defaultConfig);
    this.defaultColumns = window['appConfig'].defaultColumns;
    this.socialFeedMobile = window['appConfig'].socialFeedMobile;
    this.loading = window['appConfig'].loading;
  }

  public set columns(value: number) {
    this._columns.next(value);
    this.changeLayout();
  }

  public get columns(): number {
    return this._columns.getValue()
  }

  getBlocksContents(): Observable<Object> {
    return this.http
      .get(this.apiBaseUrl + this.getBlocksContentUrl)
      .map(response => response.json());
  }

  getBlockContent(block) {
    let content: string;

    if (typeof this.blocksContents !== 'undefined' && typeof this.blocksContents[block.id] !== 'undefined') {
      if (this.blocksContents[block.id]) {
        content = this.blocksContents[block.id];
      }
    }
    return content;
  }

  getPositioning(): Observable<Object> {
    let url = '';

    if (this.apiRouteName == 'opigno_dashboard.dashboard_admin_default_settings') {
      url = this.getDefaultPositioningUrl;
    }
    else {
      url = this.getPositioningUrl;
    }

    // Add headers to remove this query caching.
    let headers = new Headers();
    headers.append('Cache-Control', 'no-cache, no-store, must-revalidate, post-check=0, pre-check=0');
    headers.append('Pragma', 'no-cache');
    headers.append('Expires', '0');

    return this.http
      .get(this.apiBaseUrl + url, { headers: headers })
      .map(response => response.json());
  }

  setPositioning(): void {
    let datas = {};
    let url = '';

    datas['positions'] = this.positions;
    datas['columns'] = this.columns;
    datas['apiRouteName'] = this.apiRouteName;

    if (this.apiRouteName == 'opigno_dashboard.dashboard_admin_default_settings') {
      url = this.setDefaultPositioningUrl;
    }
    else {
      url = this.setPositioningUrl;
    }

    this.http
      .post(this.apiBaseUrl + url, JSON.stringify(datas))
      .subscribe(data => {
        /** ... */
      }, error => {
        console.error(error);
      });
  }

  changeLayout(): void {

    let nbColumns;
    if (this.columns == 4) {
      nbColumns = 3;
    } else if (this.columns == 3) {
      nbColumns = 2;
    } else {
      nbColumns = this.columns;
    }

    // Check if there is content in hidden columns
    for (let i = 0; i < this.positions.length; i++) {
      if (i > nbColumns && this.positions[i].length) {
        // Put content in last column
        this.positions[nbColumns] = this.positions[nbColumns].concat(this.positions[i]);

        // Clear removed column
        this.positions[i] = [];
      }
    }

    this.setPositioning();
    this.loading = false;
  }

  restoreToDefaultAll(): void {
    let url = this.restoreToDefaultAllUrl;

    if (confirm('Warning! You going to set all users dashboard to default configuration!')) {
      this.http
      .post(this.apiBaseUrl + url, '')
      .subscribe(data => {
        /** ... */
      }, error => {
        console.error(error);
      });
    }
  }

  displayRestoreToDefaultButton() {
    return this.apiRouteName !== 'opigno_dashboard.dashboard_admin_default_settings';
  }

  reinit() {
    this.columns = this.defaultColumns;

    // Put all blocks in admin panel
    for (let column in this.positions) {
      if (column != '0') {
        for (let row in this.positions[column]) {
          this.positions[0].push(this.positions[column][row]);
        }
      }
    }

    // Put default blocks in columns and remove them from admin panel
    for (let i = 1; i <= 3; i++) {
      this.positions[i] = JSON.parse(JSON.stringify(this.defaultConfig[i]));

      this.defaultConfig[i].forEach((defaultConfigBlocks) => {
        this.positions[0] = this.positions[0].filter(block => block.id !== defaultConfigBlocks.id);
      });
    }

    this.setPositioning();
  }

  /** Workaround used for 1..n loops */
  range(value): Array<number> {
    let a = [];

    for (let i = 0; i < value; ++i) {
      a.push(i + 1)
    }

    return a;
  }

  closeManageDashboard() {
    this.managePanel = false;

    setTimeout(() => {
      window['Drupal'].attachBehaviors(document.querySelector('.dashboard'), window['Drupal'].settings);
    });
  }
}
