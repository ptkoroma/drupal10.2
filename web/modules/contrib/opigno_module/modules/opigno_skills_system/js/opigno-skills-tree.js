(function ($, Drupal) {
  Drupal.behaviors.opignoStatisticsPopover = {
    attach: function (context) {

      var trees_array = drupalSettings.opigno_skills_tree;
      var skills = null;
      var arrows = null;
      var network = null;

      var LENGTH_MAIN = 150,
        LENGTH_SERVER = 150,
        LENGTH_SUB = 50,
        WIDTH_SCALE = 2,
        GREEN = 'green',
        RED = '#C5000B',
        ORANGE = 'orange',
        GRAY = 'gray',
        BLACK = '#2B1B17';

      var options = {
        nodes: {
          shape: "dot",
          scaling: {
            min: 16,
            max: 32
          },
          font:{
            size: 16,
          },
          widthConstraint: { maximum: 140 },
        },
        edges: {
          smooth: false,
          font:{
            size: 14,
            align: 'middle',
          },
        },
        layout: {
          hierarchical: {
            enabled: true,
            direction: "LR",
            sortMethod: "directed"
          }
        },
        "physics": {
          enabled: false,
          "hierarchicalRepulsion": {
            "centralGravity": 0.5
          },
          "minVelocity": 0.75,
          "solver": "hierarchicalRepulsion"
        },
        interaction: {
          dragNodes: false,
          zoomView: false,
          dragView: false
        },
        groups: {
          'pending': {
            color: "#2B7CE9"
          },
          'done': {
            color: "#109618"
          },
        }
      };

      $.each(trees_array, function(key) {
        skills = [];
        arrows = [];

        $.each(this.skills, function() {
          skills.push({
            id: this.id,
            label: this.label,
            group: this.group,
            value: 10,
            title: this.title
          });
        });

        $.each(this.arrows, function() {
          arrows.push({
            from: this.from,
            to: this.to,
            color: GRAY,
            arrows: "to",
            label: this.label,
          });
        });

        var container = document.getElementById('opigno-skills-tree-' + key);
        var data = {
          nodes: skills,
          edges: arrows
        };

        network = new vis.Network(container, data, options);

        $('#opigno-skills-tree-' + key).height(this.width * 100);
      });
    }
  }
}(jQuery, Drupal, drupalSettings));
