<?php

//Redirect bei yourbmacloud
include("config.inc.php");
if($yourbmacloud==true)
{
	header('Location: login.php');
	exit;
}



?><!DOCTYPE html>
<!--[if lt IE 7]> <html class="ie ie6 lt-ie9 lt-ie8 lt-ie7" lang="en"> <![endif]-->
<!--[if IE 7]>    <html class="ie ie7 lt-ie9 lt-ie8"        lang="en"> <![endif]-->
<!--[if IE 8]>    <html class="ie ie8 lt-ie9"               lang="en"> <![endif]-->
<!--[if IE 9]>    <html class="ie ie9"                      lang="en"> <![endif]-->
<!--[if !IE]><!-->
<html lang="en" class="no-ie">
<!--<![endif]-->

<head>
   <!-- Meta-->
   <meta charset="utf-8">
   <meta http-equiv="X-UA-Compatible" content="IE=edge">
   <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=0">
   <meta name="description" content="Erstellen Sie Ihre Pr&uuml;fplane Online und bei der Wartung f&uuml;llen Sie diese per App aus. Die Meldungen der Brandmeldezentrale werden über eine Wartungsbox auf das Smartphone des Wartungstechnikers übertragen und in der Cloud gespeichert">
   <meta name="keywords" content="BMAcloud, Brandmeldeanlage, Wartungsbox, DIN14675, BMA-Wartung, Esser-App, NSC-App, Wartungsapp, Wartung, Schraner">
   <meta name="author" content="Oliver K&ouml;nigs">
   <title>BMAcloud - App zur Wartung von Brandmeldeanlagen nach DIN14675 für Errichter</title>
   <!-- HTML5 shim and Respond.js IE8 support of HTML5 elements and media queries -->
   <!--[if lt IE 9]><script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script><script src="https://oss.maxcdn.com/libs/respond.js/1.4.2/respond.min.js"></script><![endif]-->
   <!-- Bootstrap CSS-->
   <link rel="stylesheet" href="app/css/bootstrap.css">
   <!-- Vendor CSS-->
   <link rel="stylesheet" href="vendor/fontawesome/css/font-awesome.min.css">
   <link rel="stylesheet" href="vendor/animo/animate+animo.css">
   <!-- App CSS-->
   <link rel="stylesheet" href="app/css/app.css">
   <!-- Modernizr JS Script-->
   <script src="vendor/modernizr/modernizr.js" type="application/javascript"></script>
   <!-- FastClick for mobiles-->
   <script src="vendor/fastclick/fastclick.js" type="application/javascript"></script>
   <link rel="stylesheet" href="app/css/landing.css">
</head>
<?php

$useragent = $_SERVER['HTTP_USER_AGENT'];

if (preg_match("/(alcatel|amoi|android|avantgo|blackberry|benq|cell|cricket|docomo|elaine
|htc|iemobile|iphone|ipad|ipaq|ipod|j2me|java|midp|mini|mmp|mobi|motorola|nec-|nokia|palm|
panasonic|philips|phone|playbook|sagem|sharp|sie-|silk|smartphone|sony|symbian|t-mobile|telus
|up\.browser|up\.link|vodafone|wap|webos|wireless|xda|xoom|zte)/i",$useragent))
{
$loginurl = 'https://www.bmacloud.de/login.php?mobile=1';
} else {
$loginurl = 'https://www.bmacloud.de/login.php';
}

?>
<body>
   <header>
      <div class="container">
         <nav class="row">
            <div class="col-md-2 app-logo">
               <a href="#" class="logo-wrapper">
                  <img src="app/img/logo3.png" alt="App Name" class="img-responsive">
               </a>
            </div>
            <div class="col-md-10">
               <ul class="list-inline app-buttons">
                  <li>
                     <a href="<?=$loginurl ?>" class="btn btn-danger">
                        <strong>Login</strong>
                     </a>
                  </li>
               </ul>
            </div>
         </nav>
         <div class="header-content">
            <div class="row row-flush row-table">
               <div class="col-xs-12 col-lg-6 align-middle">
                  <div data-toggle="play-animation" data-play="fadeInLeft" data-offset="0" class="browser-presentation">
                     <img src="app/img/landing/bmacloud_start.jpg" alt="BMAcloud Screenshot" class="img-responsive">
                  </div>
               </div>
               <div class="col-xs-12 col-lg-6 align-middle">
                  <div class="side-presentation">
                     <h1 class="text-lg">BMAcloud f&uuml;r Errichter</h1>
                     <p class="lead">Erstellen Sie Ihre Pr&uuml;fpl&auml;ne online und bei der Wartung f&uuml;llen Sie diese per App aus</p>
                     <ul class="list-inline store-list">
                        <li>
                           <a href="https://geo.itunes.apple.com/de/app/bmacloud/id1083945698?mt=8">
                              <img src="app/img/landing/store-apple.png" alt="BMAcloud App" class="img-responsive">
                           </a>
                        </li>
                        <li>
                           <a href="https://play.google.com/store/apps/details?id=com.bmacloud.app">
                              <img src="app/img/landing/store-google.png" alt="BMAcloud App" class="img-responsive">
                           </a>
                        </li>
                     </ul>
                  </div>
               </div>
            </div>
         </div>
      </div>
   </header>
   <section id="clients">
      <div class="container">
         <h2 class="section-header">Herstellerunabh&auml;ngig
            <br>
            <small class="text-muted text-center">Die Hardware f&uuml;r die BMAcloud ist an keinen Zentralenhersteller gebunden</small>
         </h2>
      </div>
   </section>
   <section class="bg-white">
      <div class="container">
         <div class="row">
            <div class="col-lg-6">
               <h2 class="page-header">Dateiverwaltung</h2>
               <p>Die Anlagenprogrammierung, Laufkarten und Verteilerpl&auml;ne k&ouml;nnen zu jeder Anlage gespeichert werden.</p>
               <p>Die Daten k&ouml;nnen 24 Stunden am Tag durch jeden Techniker abgerufen und aktualisiert werden</p>
            </div>
            <div data-toggle="play-animation" data-play="fadeInRight" data-offset="-250" class="col-lg-6">
               <img src="app/img/landing/bmacloud_dateiverwaltung.jpg" alt="BMAcloud Dateiverwaltung" class="img-responsive">
            </div>
         </div>
      </div>
   </section>
   <section>
      <div class="container">
         <div class="row">
            <div data-toggle="play-animation" data-play="fadeInLeft" data-offset="-250" class="col-lg-6">
               <img src="app/img/landing/bmacloud_pruefplan.jpg" alt="BMAcloud Pr&uuml;fplan" class="img-responsive">
            </div>
            <div class="col-lg-6">
               <h2 class="page-header">Pr&uuml;fpl&auml;ne</h2>
               <p>Das Kernelement der BMAcloud ist der Wartungspr&uuml;fplan. F&uuml;r jedes Wartungsintervall werden automatisch die Pr&uuml;fpl&auml;ne berechnet. Diese k&ouml;nnen aus der Anlagenprogrammierung eingelesen werden.</p>
			   <p>Jeder Pr&uuml;fplan kann individuell auf eine Anlage abgestimmt werden</p>
            </div>
         </div>
      </div>
   </section>
   <section class="bg-white">
      <div class="container">
         <div class="row">
            <div class="col-lg-6">
               <h2 class="page-header">Live-Meldungen</h2>
               <p>Jedes Ereignis der BMZ kann an die BMAcloud gesendet werden. Daher haben Sie jederzeit einen &Uuml;berblick &uuml;ber den aktuellen Stand Ihrer Brandmeldeanlagen.</p>
               <p>W&auml;hrend der Wartung werden alle Ausl&ouml;sungen dokumentiert. Au&szlig;erhalb der Wartung k&ouml;nnen die Live-Meldungen f&uuml;r Servicezwecke verwendet werden.</p>
            </div>
            <div data-toggle="play-animation" data-play="fadeInRight" data-offset="-250" class="col-lg-6">
               <img src="app/img/landing/bmacloud_livemeldungen.jpg" alt="BMAcloud Livemeldungen" class="img-responsive">
            </div>
         </div>
      </div>
   </section>
   <section id="testimonial" class="bg-primary">
      <div class="container">
         <div id="carousel-testimonial" data-ride="carousel" class="carousel slide">
            <!-- Indicators -->
            <ol class="carousel-indicators">
               <li data-target="#carousel-testimonial" data-slide-to="0" class="active"></li>
               <li data-target="#carousel-testimonial" data-slide-to="1"></li>
               <li data-target="#carousel-testimonial" data-slide-to="2"></li>
            </ol>
            <!-- Wrapper for slides -->
            <div class="carousel-inner">
               <div class="item active">
                  <div class="row">
                     <div class="col-xs-8 col-xs-offset-2">
                        <h4>
                           <em>Wie stellen Sie w&auml;hrend der Wartung sicher, dass ein Echtalarm aus dem Verwaltungsgeb&auml;ude auch gemeldet wird, wenn die Brandfallsteuerungen abgeschaltet sind und die Wartung gerade in der Produktionshalle durchgef&uuml;hrt wird?</em>
                        </h4>
                        <p>
                           <strong>durch die Meldung auf dem Smartphone des Wartungstechnikers</strong>
                        </p>
                     </div>
                  </div>
               </div>
               <div class="item">
                  <div class="row">
                     <div class="col-xs-8 col-xs-offset-2">
                        <h4>
                           <em>Woher wei&szlig; ein neuer Kollege, wo sich der zu wartende Melder im Geb&auml;ude befindet?</em>
                        </h4>
                        <p>
                           <strong>durch die Laufkartenanzeige in der Wartungsapp</strong>
                        </p>
                     </div>
                  </div>
               </div>
               <div class="item">
                  <div class="row">
                     <div class="col-xs-8 col-xs-offset-2">
                        <h4>
                           <em>Wie stellen Sie sicher, dass in einem Jahr auch jeder Melder mindestens 1x ausgel&ouml;st wurde? Wie und mit welchem Aufwand ist das dokumentiert?</em>
                        </h4>
                        <p>
                           <strong>Durch automatisch berechnete Pr&uuml;fpl&auml;ne</strong>
                        </p>
                     </div>
                  </div>
               </div>
            </div>
            <!-- Controls -->
            <a href="#carousel-testimonial" data-slide="prev" class="left carousel-control">
               <em class="fa fa-chevron-circle-left"></em>
            </a>
            <a href="#carousel-testimonial" data-slide="next" class="right carousel-control">
               <em class="fa fa-chevron-circle-right"></em>
            </a>
         </div>
      </div>
   </section>
   <section class="bg-white">
      <div class="container">
         <h2 data-toggle="play-animation" data-play="fadeInUp" data-offset="0" class="section-header">Features der BMAcloud
            <br>
            <small class="text-muted text-center">Perfekt f&uuml;r die Bed&uuml;rfnisse der Errichter.</small>
         </h2>
         <div class="row">
            <div class="col-lg-3">
               <ul class="feature-list">
                  <li data-toggle="play-animation" data-play="fadeInUp" data-offset="0">
                     <h4>
                        <span class="point point-primary point-lg"></span>Pr&uuml;fpl&auml;ne</h4>
                     <p>Automatisch generiert aus der Anlagenprogrammierung</p>
                  </li>
                  <li data-toggle="play-animation" data-play="fadeInUp" data-offset="0">
                     <h4>
                        <span class="point point-primary point-lg"></span>Live-Meldungen</h4>
                     <p>W&auml;hrend der Wartung oder durch fest installierte Sender</p>
                  </li>
                  <li data-toggle="play-animation" data-play="fadeInUp" data-offset="0">
                     <h4>
                        <span class="point point-primary point-lg"></span>App</h4>
                     <p>F&uuml;r den Wartungstechniker mit den wichtigsten Informationen</p>
                  </li>
                  <li data-toggle="play-animation" data-play="fadeInUp" data-offset="0">
                     <h4>
                        <span class="point point-primary point-lg"></span>Laufkarten</h4>
                     <p>Immer verf&uuml;gbar</p>
                  </li>
               </ul>
            </div>
            <div class="col-lg-6">
               <img src="app/img/landing/app-mobile.png" alt="App Name" class="img-responsive">
            </div>
            <div class="col-lg-3">
               <ul class="feature-list">
                  <li data-toggle="play-animation" data-play="fadeInUp" data-offset="0">
                     <h4>
                        <span class="point point-primary point-lg"></span>Hardware</h4>
                     <p>Mobil oder station&auml;r, per UMTS oder Ethernet</p>
                  </li>
                  <li data-toggle="play-animation" data-play="fadeInUp" data-offset="0">
                     <h4>
                        <span class="point point-primary point-lg"></span>Auswertungen</h4>
                     <p>PDF-Pr&uuml;fplan f&uuml;r den Kunden</p>
                  </li>
                  <li data-toggle="play-animation" data-play="fadeInUp" data-offset="0">
                     <h4>
                        <span class="point point-primary point-lg"></span>Intervalle</h4>
                     <p>Individuell pro Melder einstellbar um Laufwege zu optimieren</p>
                  </li>
                  <li data-toggle="play-animation" data-play="fadeInUp" data-offset="0">
                     <h4>
                        <span class="point point-primary point-lg"></span>Dateiverwaltung</h4>
                     <p>Zu jeder Anlage und f&uuml;r jeden Techniker per Notebook erreichbar</p>
                  </li>
               </ul>
            </div>
         </div>
      </div>
   </section>
   <section id="callout">
      <div data-toggle="play-animation" data-play="fadeInLeftBig" data-offset="-200" class="container text-center">
         <h1>Sind Sie bereit?</h1>
         <h4>um die Wartung von Brandmeldeanlagen mit mehr Effizienz und Sicherheit durchzuf&uuml;hren</h4>
         <ul class="list-inline store-list">
            <li>
                           <a href="https://geo.itunes.apple.com/de/app/bmacloud/id1083945698?mt=8">
                              <img src="app/img/landing/store-apple.png" alt="BMAcloud App" class="img-responsive">
                           </a>
            </li>
            <li>
                           <a href="https://play.google.com/store/apps/details?id=com.bmacloud.app">
                              <img src="app/img/landing/store-google.png" alt="BMAcloud App" class="img-responsive">
                           </a>
            </li>
         </ul>
         <p>
            <br>
            <a href="mailto:info@bmacloud.de" style="width: 180px" class="btn btn-primary btn-large btn-oval">
               <strong>Kontakt aufnehmen</strong>
            </a>
         </p>
      </div>
   </section>
   <!-- <footer class="footer-1 bg-inverse">
      <div class="container">
         <div class="row">
            <div>
<div class="alert alert-success" id="n_ok" style="visibility: hidden;">
Die Adresse wurde erfolgreich f&uuml;r den Newsletter angemeldet
</div>
<div class="alert alert-danger" id="n_err" style="visibility: hidden;">
Die Adresse ist ung&uuml;ltig!
</div>
               <form action="" method="post">
                  <h5>REGISTRIEREN SIE SICH F&uuml;R DEN NEWSLETTER!</h5>
                  <div class="input-group">
                     <input type="email" name="email" placeholder="ihre@email.de" required="" class="form-control">
                     <span class="input-group-btn">
                        <button type="submit" class="btn btn-success">Anmelden</button>
                     </span>
                  </div>
               </form>
               <p class="text-muted">
                  <small>Wir Informieren Sie &uuml;ber Updates und aktuelle Themen der BMAcloud</small>
               </p>
            </div>
         </div>
      </div>
   </footer> -->
   <footer class="footer-2">
      <div class="container">
         <div class="row">
            <div class="col-lg-4">
               <p><a href="impressum.php">Impressum</a></p>
			   <p><a href="https://plus.google.com/118057893006390625295" rel="publisher">Google+</a></p>
            </div>
         </div>
      </div>
   </footer>
   <!-- END wrapper-->
   <!-- START Scripts-->
   <!-- Main vendor Scripts-->
   <script src="vendor/jquery/jquery.min.js"></script>
   <script src="vendor/bootstrap/js/bootstrap.min.js"></script>
   <!-- Animo-->
   <script src="vendor/animo/animo.min.js"></script>
   <!-- Custom script for pages-->
   <script src="app/js/pages.js"></script>
   <!-- END Scripts-->
   <script>
  (function(i,s,o,g,r,a,m){i['GoogleAnalyticsObject']=r;i[r]=i[r]||function(){
  (i[r].q=i[r].q||[]).push(arguments)},i[r].l=1*new Date();a=s.createElement(o),
  m=s.getElementsByTagName(o)[0];a.async=1;a.src=g;m.parentNode.insertBefore(a,m)
  })(window,document,'script','//www.google-analytics.com/analytics.js','ga');

  ga('create', 'UA-55441218-1', 'auto');
  ga('send', 'pageview');

</script>
</body>

</html>