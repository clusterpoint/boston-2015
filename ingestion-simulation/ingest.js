require('./config.js');
var cps = require('cps-api');
var fs = require('fs');
var successfulTransactions = 0;
var scriptIntervals = [],
    intervalTime = 50, //50ms - 20 requests per second
    idArray = [],
    filename = 'rep-ids-1-n'; //file with random Wikipedia article IDs

fs.readFile(filename, 'utf8', function (err, data) {
    if (err) return console.log(err);
    idArray = data.split('\n');
    for(var j, x, i = idArray.length; i; j = Math.floor(Math.random() * i), x = idArray[--i], idArray[i] = idArray[j], idArray[j] = x);
        start();
});


function proceedTransaction(id) {
    try {
        var conn = new cps.Connection(CONFIG.cps.host, CONFIG.cps.db, CONFIG.cps.username, CONFIG.cps.password, 'page', 'page/id', {account: CONFIG.cps.account_id});

        conn.sendRequest(new cps.BeginTransactionRequest(), function (err, response) {
            //transaction started
            if (err) return console.log('Begin: '+err); //throw exception
            var retrieve_req = new cps.RetrieveRequest(id); //retrieve record with specified ID
            //perform search query
            conn.sendRequest(retrieve_req, function (err, retrieve_resp) {
                if (err) return console.log('Read: ',err,retrieve_req,conn); //throw exception
                if (!retrieve_resp) {
                    return false;
                }
                var document = retrieve_resp.results.page[0],
                    d,t;
                var tzoffset = (new Date()).getTimezoneOffset() * 60000;
                d = new Date(Date.now() - tzoffset)
                t = d.toISOString();
                t = t.replace(/T|Z/g, ' ');
                t = t.replace(/-/g, '/');
                t = t.split('.')[0];
                document.revision.timestamp = t;
                document.revision.text += " + #Clusterpoint";
                //Update document
                conn.sendRequest(new cps.UpdateRequest(document), function (err, resp) {
                    if (err) return console.log('Update: ',err,conn); //throw exception
                    conn.sendRequest(new cps.CommitTransactionRequest(), function (err, response) {// lets commit!
                        if (err) return console.log('Commit: ',err,conn); //throw exception
                        successfulTransactions++;
                    }, 'json');
                });

            });
        }, 'json');
    } catch (e) {
        console.log(e);
        conn.sendRequest(new cps.RollbackTransactionRequest(), function(err, response) {
            console.log('Transaction rolled back');
        });

    }
}

function runScript() {
id = idArray.pop();
    if (id) {
        proceedTransaction(id);
    }
}

function start() {
    successfulTransactions = 0;
    setInterval(runScript, intervalTime);
    setInterval(function() {console.log('Successful transactions: ' + successfulTransactions); successfulTransactions = 0}, 1000);
}

console.log('Node.JS server started!');
