(function (Drupal, $, once) {
  Drupal.behaviors.dblogLiveTime = {
    attach: function (context, settings) {
      $(once('dblogLiveTime', '#dblog-server-time', context)).each(function () {
        const $timeEl = $(this);

        function updateTime() {
          let current = new Date($timeEl.text().replace(' ', 'T'));
          if (isNaN(current.getTime())) {
            current = new Date();
          }

          current.setSeconds(current.getSeconds() + 1);

          const formatted = current.getFullYear() + '-' +
            String(current.getMonth() + 1).padStart(2, '0') + '-' +
            String(current.getDate()).padStart(2, '0') + ' ' +
            String(current.getHours()).padStart(2, '0') + ':' +
            String(current.getMinutes()).padStart(2, '0') + ':' +
            String(current.getSeconds()).padStart(2, '0');

          $timeEl.text(formatted);
        }

        setInterval(updateTime, 1000);
      });
    }
  };
})(Drupal, jQuery, once);
