(function (Icinga) {

    var Eventtracker = function (module) {
        this.module = module;
        this.initialize();
        this.module.icinga.logger.debug('EventTracker module loaded');
    };

    Eventtracker.prototype = {
        initialize: function () {
            this.module.on('mouseover', 'thead tr', this.checkForHeaderHref);
            this.module.on('rendered', this.rendered);
        },

        checkForHeaderHref: function (ev) {
            // href will be added because of sort icons
            $(ev.currentTarget).removeAttr('href');
        },


        rendered: function (event) {
            let $container = $(event.currentTarget);
            if (this.isAdvancedUpload()) {
                this.initializeFiles($container);
                $container.find('.eventtracker-file-drop-zone').on('change', this.droppedFiles);
            } else {
                $container.find('.eventtracker-file-drop-zone').remove();
            }
        },

        initializeFiles: function ($container) {
            let droppedFiles = false;
            let $dropZone = $container.find('.eventtracker-file-drop-zone');
            let $form = $dropZone.closest('form');

            $dropZone.on('drag dragstart dragend dragover dragenter dragleave drop', function(e) {
                e.preventDefault();
                e.stopPropagation();
            })
                .on('dragover dragenter', function() {
                    $dropZone.addClass('is-dragover');
                })
                .on('dragleave dragend drop', function() {
                    $dropZone.removeClass('is-dragover');
                })
                .on('drop', function(e) {
                    droppedFiles = e.originalEvent.dataTransfer.files;
                    //console.log(droppedFiles);
                    let $input = $form.find('input[type="file"]');
                    $input.prop('files', droppedFiles);
                    $form.submit();
                });

        },

        isAdvancedUpload: function () {
            var div = document.createElement('div');
            return (('draggable' in div)
                    || ('ondragstart' in div && 'ondrop' in div))
                && 'FormData' in window && 'FileReader' in window;
        },

        droppedFiles: function(event){
            console.log('Triggered');
            event.preventDefault();
            event.stopPropagation();
            var files = event.target.files;
            $('#drop').css('display', 'none');
            for(var i = 0, len = files.length; i < len; i++) {
                if(files[i].type === 'text/plain' || files[i].type === ''){
                    $.ajax({
                        type: "POST",
                        url: "uploader.php?id="+i,
                        contentType: "multipart/form-data",
                        headers: {
                            "X-File-Name" : files[i].name,
                            "X-File-Size" : files[i].size,
                            "X-File-Type" : files[i].type
                        }
                    });
                }else{
                    $('#info').append('Content type must be text/plain');
                }
            }
        }
    };

    Icinga.availableModules.eventtracker = Eventtracker;

}(Icinga));
