(function (Icinga) {

    var Eventtracker = function (module) {
        this.module = module;
        this.initialize();
        this.module.icinga.logger.debug('EventTracker module loaded');
    };

    Eventtracker.prototype = {
        initialize: function () {
            this.module.on('mouseover', 'thead tr', this.checkForHeaderHref);
        },

        checkForHeaderHref: function (ev) {
            // href will be added because of sort icons
            $(ev.currentTarget).removeAttr('href');
        }
    };

    Icinga.availableModules.eventtracker = Eventtracker;

}(Icinga));
