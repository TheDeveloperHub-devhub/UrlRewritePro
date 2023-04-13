define([
    'Magento_Ui/js/form/components/button',
], function (Button) {
    'use strict';
    return Button.extend({
        /**
         * Show element.
         */
        show: function () {
            this.visible(true);
            return this;
        },

        /**
         * Hide element.
         */
        hide: function () {
            this.visible(false);
            return this;
        },
    });
});
