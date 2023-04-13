
define([
    'Magento_Ui/js/dynamic-rows/dynamic-rows-grid',
    'underscore',
    'prototype'
], function (dynamicRowsGrid, _) {
    'use strict';

    return dynamicRowsGrid.extend({
        _updateData: function (data) {
            this._super();
            _.each(this.elems(), function (record) {
                _.each(record.elems(), function (elem) {
                   if (_.isFunction(elem.checkState)) {
                       elem.checkState();
                   }
                });
            });
        }
    });
});
