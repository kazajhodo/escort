/**
 * @file
 * Global Escort javascript.
 */

(function ($, document) {

  'use strict';

  function Escort(region) {
    this.$region = $(region);
    this.region = this.$region.data('region');
    this.$body = $('body');
    this.setup();
  }

  $.extend(Escort, /** @lends Drupal.Escort */{

    /**
     * Holds references to instantiated Escort objects.
     *
     * @type {Array.<Drupal.Escort>}
     */
    instances: []
  });

  $.extend(Escort.prototype, /** @lends Drupal.Escort# */{
    full: false,

    setup: function () {
      var _this = this;
      _this.$region.hover(function (e) {
        e.preventDefault();
        _this.showFull();

        // Bind body click event.
        _this.$body.on('click.escort-' + _this.region, function (e) {
          if (_this.full && !$(e.target).closest(_this.$region).length) {
            _this.hideFull();
          }
        });
      });
    },

    showFull: function () {
      if (!this.full) {
        this.full = true;
        this.$body.addClass('show-escort-full-' + this.region);
      }
    },

    hideFull: function () {
      if (this.full) {
        this.full = false;
        this.$body.removeClass('show-escort-full-' + this.region);
        this.$body.off('click.escort-' + this.region);
      }
    }
  });

  Drupal.behaviors.escort = {
    attach: function (context) {
      var $escortRegion = $(context).find('.escort-region').once('escort-region').addClass('escort-region-processed');
      if ($escortRegion.length) {
        for (var i = 0; i < $escortRegion.length; i++) {
          Escort.instances.push(new Escort($escortRegion[i]));
        }
        setTimeout(function () {
          $('body').addClass('escort-ready');
        }, 10);
      }
    }
  };

  // Expose constructor in the public space.
  Drupal.Escort = Escort;

}(jQuery, document));
