//SimileAjax_urlPrefix = document.URL.substr(0, document.URL.lastIndexOf('/')) + '/js/simile-ajax/';
Timeplot_urlPrefix = document.URL.substr(0, document.URL.lastIndexOf('/')) + '/js/timeplot/';
Timeplot_ajax_url = document.URL.substr(0, document.URL.lastIndexOf('/')) + "/js/simile-ajax/simile-ajax-api.js"
function init_timeplot(Y, tcountid, user) {
    if ("Timeline" in window) {
        deferred_init_timeplot(Y, tcountid, user);
    } else {
        setTimeout(init_timeplot, 100, Y, tcountid, user);
    }
}
function deferred_init_timeplot(Y, treasureid, user) {

    var eventSource = new Timeplot.DefaultEventSource();
    SimileAjax.History.enabled = false;



    var timeGeometry = new Timeplot.DefaultTimeGeometry({
        gridColor: new Timeplot.Color("#222222"),
        axisLabelsPlacement: "top"
    });

    var valueGeometry = new Timeplot.DefaultValueGeometry({
        gridColor: "#222222",
        min: 0,
        max: 11
    });

    var plotInfo = [
        Timeplot.createPlotInfo({
            id: "plot1",
            dataSource: new Timeplot.ColumnSource(eventSource, 1),
            timeGeometry: timeGeometry,
            valueGeometry: valueGeometry,
            lineColor: "#ff0000",
            fillColor: "#cc8080",
            showValues: true
        }),
        Timeplot.createPlotInfo({
            id: "plot2",
            dataSource: new Timeplot.ColumnSource(eventSource, 2),
            timeGeometry: timeGeometry,
            valueGeometry: valueGeometry,
            lineColor: "#00ff00",
//            fillColor: "#80cc80",
            showValues: true
        }),
        Timeplot.createPlotInfo({
            id: "plot3",
            dataSource: new Timeplot.ColumnSource(eventSource, 3),
            timeGeometry: timeGeometry,
            valueGeometry: valueGeometry,
            lineColor: "#D0A825",
//            fillColor: "#dcb010",
            showValues: true
        }),
        Timeplot.createPlotInfo({
            id: "plot4",
            dataSource: new Timeplot.ColumnSource(eventSource, 4),
            timeGeometry: timeGeometry,
            valueGeometry: valueGeometry,
            lineColor: "#0000ff",
            //  fillColor: "#8080cc",
            showValues: true
        }),
    ];
    timeplot = Timeplot.create(document.getElementById("treasure-timeplot"), plotInfo);
    timeplot.loadText("plotted.php?treasure=" + treasureid, ";", eventSource);
}