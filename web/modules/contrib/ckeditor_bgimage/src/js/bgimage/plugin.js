/**
 * @file
 * Drupal File plugin.
 *
 * @ignore
 */

(function ($, Drupal, drupalSettings, CKEDITOR) {
  'use strict';

  CKEDITOR.plugins.add('bgimage', {
    init: function (editor) {
      // Add the commands for file and unfile.
      editor.addCommand('bgimage', {
        allowedContent: {
          a: {
            attributes: {
              '!href': true,
              '!data-entity-type': true,
              '!data-entity-uuid': true
            },
            classes: {}
          }
        },
        requiredContent: new CKEDITOR.style(
          {
            element: 'a',
            attributes: {
              'href': '',
              'data-entity-type': '',
              'data-entity-uuid': ''
            }
          }
        ),
        modes: {wysiwyg: 1},
        canUndo: true,
        exec: function (editor) {
          // Set existing values for future updates.
          let instances = CKEDITOR.instances;
          let firstInstanceKey = Object.keys(instances)[0];
          let instance = instances[firstInstanceKey];
          let editorBody = instance.getData();
          let element = jQuery(editorBody);
          let firstDiv = element[0];

          let imageSrc = '';
          let imageStyle = '';
          let backgroundColor = '';
          let backgroundPosition = '';
          if (firstDiv && firstDiv.classList.contains('background-image') && firstDiv.firstChild.tagName !== undefined && firstDiv.firstChild.tagName === 'IMG') {
            imageSrc = firstDiv.firstChild.getAttribute("src");
            imageStyle = firstDiv.firstChild.getAttribute("style");

            let style = firstDiv.getAttribute('style');
            let matches = style.match(/background-color:((.|\n)*?);/);
            if (matches) {
              backgroundColor = matches[1];
            }
            matches = style.match(/text-align:((.|\n)*?);/);
            if (matches) {
              backgroundPosition = matches[1];
            }
          }

          var existingValues = {
            file: imageSrc,
            style: imageStyle,
            color: backgroundColor,
            position: backgroundPosition
          };

          // Prepare a save callback to be used upon saving the dialog.
          let saveCallback = function (returnValues) {
            editor.fire('saveSnapshot');

            /* config_image */
            let image = returnValues.attributes.image ? returnValues.attributes.image : '';
            let entity_uuid = returnValues.attributes.data_entity_uuid ? returnValues.attributes.data_entity_uuid : '';
            let entity_type = returnValues.attributes.data_entity_type ? returnValues.attributes.data_entity_type : '';
            let backgroundColor = returnValues.background_color;
            let width = '';
            let height = '';
            let position = returnValues.background_aling;
            if (returnValues.width !== '') {
              width = returnValues.width;
            }
            if (returnValues.height !== '') {
              height = returnValues.height;
            }
            let content = editor.document.getBody().getHtml();
            let matches = content.match(/<div class="editor-content" style="padding:15px;">((.|\n)*?)<\/div>/);

            // styled div already exists
            if (matches) {
              content = matches[1];
            }

            position = position.split(' ');

            let backgroundImageDiv = '<div class="background-image" style="position:absolute;z-index:-1;width:100%;height:100%;text-align:' + position[0] + ';background-color:' + backgroundColor + ';">';
            backgroundImageDiv += '<img data-entity-type="' + entity_type + '" data-entity-uuid="' + entity_uuid + '" src="' + image + '" style="width:' + width + ';height:' + height + ';">';
            backgroundImageDiv += '</div>';

            let contentDiv = '<div class="editor-content" style="padding:15px;">' + content + '</div>';

            editor.setData(backgroundImageDiv + contentDiv);

            // Save snapshot for undo support.
            editor.fire('saveSnapshot');
          };
          // Drupal.t() will not work inside CKEditor plugins because CKEditor
          // loads the JavaScript file instead of Drupal. Pull translated
          // strings from the plugin settings that are translated server-side.
          let dialogSettings = {
            title: editor.config.bgimage_dialogTitleAdd,
            dialogClass: 'editor-file-dialog'
          };

          // Open the dialog for the edit form.
          Drupal.ckeditor.openDialog(editor, Drupal.url('ckeditor_bgimage/dialog/file/' + editor.config.drupal.format), existingValues, saveCallback, dialogSettings);
        }
      });

      // Add buttons for file upload.
      if (editor.ui.addButton) {
        editor.ui.addButton('bgimage', {
          label: 'Background image',
          command: 'bgimage',
          toolbar: 'insert',
          icon: this.path + 'icons/background.png'
        });
      }

      // If the "menu" plugin is loaded, register the menu items.
      if (editor.addMenuItems) {
        editor.addMenuItems({
          file: {
            label: Drupal.t('Edit File'),
            command: 'bgimage',
            group: 'link',
            order: 1
          }
        });
      }

      // If the "contextmenu" plugin is loaded, register the listeners.
      if (editor.contextMenu) {
        editor.contextMenu.addListener(function (element, selection) {
          if (!element || element.isReadOnly()) {
            return null;
          }

          let menu = {};
          if (anchor.getAttribute('href') && anchor.getChildCount()) {
            menu = {file: CKEDITOR.TRISTATE_OFF};
          }
          return menu;
        });
      }
    }
  });
})(jQuery, Drupal, drupalSettings, CKEDITOR);
