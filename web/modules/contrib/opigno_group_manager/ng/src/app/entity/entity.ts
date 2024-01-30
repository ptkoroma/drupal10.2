export class Entity {
  cid: number;
  contentType: string;
  entityId: number;
  title: string;
  parents?: any[];
  imageUrl?: string;
  imageAlt?: string;
  row?: number;
  col?: number;
  isMandatory?: any;
  in_skills_system?: number;
  maxActivitiesScore?: number;
  successScoreMin?: number;
  successScoreMinMessage?: string;
  modules_count?: number;
  translate?: any[];
  editable?: boolean;
}
