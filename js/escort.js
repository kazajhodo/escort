!function(t,o){"use strict";function e(o){this.$region=t(o),this.region=this.$region.data("region"),this.$body=t("body"),this.setup()}t.extend(e,{instances:[]}),t.extend(e.prototype,{full:!1,setup:function(){var o=this;o.$region.hover(function(e){e.preventDefault(),o.showFull(),o.$body.on("click.escort-"+o.region,function(e){o.full&&!t(e.target).closest(o.$region).length&&o.hideFull()})})},showFull:function(){this.full||(this.full=!0,this.$body.addClass("show-escort-full-"+this.region))},hideFull:function(){this.full&&(this.full=!1,this.$body.removeClass("show-escort-full-"+this.region),this.$body.off("click.escort-"+this.region))}}),Drupal.behaviors.escort={attach:function(o){var s=t(o).find(".escort-region").once("escort-region").addClass("escort-region-processed");if(s.length){for(var i=0;i<s.length;i++)e.instances.push(new e(s[i]));setTimeout(function(){t("body").addClass("escort-ready")},10)}}},Drupal.Escort=e}(jQuery,document);