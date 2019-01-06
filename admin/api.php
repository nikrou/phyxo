<!DOCTYPE html>
<html lang="en" dir="ltr">
  <head>
    <meta charset="utf-8">
    <title>Phyxo web API (web-services) explorer</title>
    <link type="text/css" rel="stylesheet" href="./css/api.css">
    <script src="./js/jquery.js"></script>
    <script src="./js/api.js"></script>
  </head>
  <body>
    <a name="top"></a>

    <div id="the_header">
      <h1>Phyxo web API (web-services) explorer</h1>
    </div> <!-- the_header -->

    <div id="the_methods">
      <h2>Available methods</h2>

      <ul id="methodsList">
      </ul>
    </div> <!-- the_methods -->

    <div id="the_page">
      <h2 id="methodName" style="display:none;"></h2>
      <h2 id="errorWrapper" style="display:none;"></h2>

      <div id="the_content">
	<form id="urlForm" style="display:none;">
	  <input type="text" name="ws_url" size="60">
	  <input type="submit" value="Go!">
	</form>

	<blockquote id="introMessage">
	  <p>
	    <b>API = Application Programming Interface.</b><br>
	    This is the way other applications can communicate with Phyxo. This feature is also know as Web Services.
	  </p>

	  <p>Examples:</p>
	  <ul>
	    <li>Wordpress (web blog software) can display random photos from a Phyxo gallery in its sidebar</li>
	    <li>Lightroom (photo management software for desktop) can create albums and upload photos to Phyxo</li>
	  </ul>

	  <p>
	    This page lists all API methods available on your Phyxo installation, part of the Phyxo core or added by third-party plugins.
	    For each method you can consult required and optional parameters, and even test them in direct live!
	  </p>

	</blockquote> <!-- introMessage -->

	<form id="methodWrapper" style="display:none;">
	  <div id="methodDescription" style="display:none;">
	    <h3>Description</h3>
	    <blockquote>
	    </blockquote>
	    <br>
	  </div> <!-- methodDescription -->

	  <div id="testForm">
	    <h3>Test</h3>
	    <blockquote>
	      <table>
		<tr>
		  <td>Request format :</td>
		  <td>
		    <select id="requestFormat">
		      <option value="get" selected>GET</option>
		      <option value="post">POST</option>
		    </select>
		  </td>
		</tr>
		<tr>
		  <td colspan="2">
		    <a href="#" class="button" id="invokeMethod">INVOKE</a>
		    <a href="#" class="button" id="invokeMethodBlank">INVOKE (new window)</a>
		  </td>
		</tr>
	      </table>
	    </blockquote>
	  </div> <!-- testForm -->

	  <div id="methodParams">
	    <h3>Method parameters</h3>
	    <table>
	      <thead>
		<tr>
		  <td style="width:150px;">Name</td>
		  <td class="mini">Extra</td>
		  <td class="mini">Type</td>
		  <td style="width:300px;">Value</td>
		  <td class="mini">Send</td>
		</tr>
	      </thead>
	      <tbody>
	      </tbody>
	      <tfoot>
		<tr>
		  <td colspan="5">
		    <b>*</b>: required, <b>?</b>: optional, <b>[]</b>: can be an array (use a pipe | to split values)<br>
                    <b>B</b>: boolean, <b>I</b>: integer, <b>F</b>: float, <b>+</b>: positive, <b>&oslash;</b>: not null
		  </td>
		</tr>
	      </tfoot>
	    </table>
	  </div> <!-- methodParams -->

	  <div id="requestDisplay" style="display:none;">
            <br>
            <h3>Request</h3>
            <blockquote>
              <pre class="url"></pre>
              <pre class="params"></pre>
            </blockquote>
          </div> <!-- requestDisplay -->

          <br>
          <h3>Result</h3>
          <div id="iframeWrapper">
            <iframe src="" id="invokeFrame" name="invokeFrame"></iframe>
            <a href="#iframe-bottom" id="increaseIframe"><b>&darr;</b> increase height</a> &#8226;
	    <a href="#iframe-bottom" id="decreaseIframe"><b>&uarr;</b> decrease height</a>
	    <a name="iframe-bottom"></a>
          </div>
        </form> <!-- iframeWrapper -->

        <!-- hidden form for POST submition -->
        <form method="post" action="" target="" id="invokeForm" style="display:none;"></form>

      </div> <!-- the_content -->
    </div> <!-- the_page -->

    <div id="the_footer">
      Copyright &copy; 2017 <a href="https://www.phyxo.net/">Phyxo</a>
    </div> <!-- the_footer -->
  </body>
</html>
