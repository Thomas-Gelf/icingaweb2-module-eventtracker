(function (Icinga) {

    var Eventtracker = function (module) {
        this.module = module;
        this.initialize();
        this.module.icinga.logger.debug('EventTracker module loaded');
    };

    Eventtracker.prototype = {
        initialize: function () {
            this.module.on('mouseover', 'thead tr', this.checkForHeaderHref);
            this.module.on('click', 'td a.control-collapsible', this.toggleCollapsibleRow);
        },

        checkForHeaderHref: function (ev) {
            // href will be added because of sort icons
            $(ev.currentTarget).removeAttr('href');
        },

        toggleCollapsibleRow: function (ev) {
            $(ev.currentTarget).closest('td.collapsible-table-row').toggleClass('collapsed');
            $('#col1').removeData('icinga-actiontable-former-href');
            ev.stopPropagation();
            ev.stopImmediatePropagation();
            ev.preventDefault();
            return false;
        }
    };

    Icinga.availableModules.eventtracker = Eventtracker;

}(Icinga));
