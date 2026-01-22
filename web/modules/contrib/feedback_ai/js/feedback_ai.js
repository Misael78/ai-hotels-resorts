(function ($, Drupal) {
  Drupal.behaviors.sentimentChart = {
    attach: function (context, settings) {
      window.onload = function () {
      var dataPoints = drupalSettings.FeedbackAi.dataPoints;
      if (dataPoints.length > 0) {
        var chart = new CanvasJS.Chart("chartContainer", {
          animationEnabled: true,
          theme: "light2",
          title: {
            text: "Sentiment Rating"
          },
          data: [{
            type: "pie",
            showInLegend: true,
            legendText: "{label}",
            indexLabelFontSize: 16,
            indexLabel: "{label} - #percent%",
            yValueFormatString: "##0.00\"%\"",
            dataPoints: dataPoints
          }],
        });
        chart.render();
      }
    }
  }
  };

})(jQuery, Drupal);

