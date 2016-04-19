<!DOCTYPE html>

<html lang="en">
	<head>
		<% base_tag %>

		<meta charset="utf-8">
		<title>REST API Documentation</title>

		<style type="text/css">
			@import url(restassured/css/RESTDocGenerator.css);
			@import url(http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.18/themes/blitzer/jquery-ui.css);
		</style>
\	</head>
	
	<body>
		<div id="container">
			<div id="content">
				<h1>Basics</h1>

				<h3>Actions</h3>

				<p>
					Actions are the URLs that respond to commands.
				</p>
				<p>
					Typically an action that lives at a given URL corresponds to a specific type, and commands to that action
					(GET, POST, PUT, etc) act on that type and respond with that type.
				</p>
				<p>
					In some situations actions will respond with a type other than the native type of that action. This will be
					marked either with a Location header or a non-2xx response code, or both
				</p>
				<h5>Common errors</h5>
				<p>
					If you attempt to access a URL that doesn't exist you will always get a 404 error. If you attempt to access
					an action that you don't have permission to access regardless of accessing user you will get a 403 error.
				</p>

				<h3>Types</h3>

				<p>
					Internally, each type is represented in a simple object oriented type system. That means each type
					has a set of fields, a type for each of those fields, and optionally a parent class that it inherits fields from.
				</p>
				<p>
					Each action takes data in a specific type, or any subtype of that type.
				</p>
				<p>
					Each action returns data in one of several specific types, or any subtype of that specific type. For any given
					action and response code there will only be one type (or subtype) returned.
				</p>
				<p>
					Types are translated to and from on-the-wire encodings from multiple different formats. You indicate the
					format of the submitted data with the "Content-Type" header, and the desired format of the response in the "Accept" header
				</p>
				<h5>Supported formats</h5>
				<ul>
					<li>application/json</li>
					<li>text/xml</li>
					<li>application/x-www-form-urlencoded (with significant limitations - see specific section)</li>
				</ul>
				<h5>Common errors</h5>
				<p>
					If you specify a type in an "Accept" header that is not supported, the system will return a "406: Not acceptable" error.
					If you specify a type in a "Content-Type" header that is not supported, the system will return a "415: Unsupported media type" error.
				</p>

				<h4>Type mapping - application/json</h4>

				<p>
					Objects become JSON hashes ("{}").
				</p>
				<p>
					JSON does not have a native way to indicate the type of an anonymous hash, so we use a custom method - optionally
					add a "$<b></b>type" field as the first member of the hash to indicate the specific type. Some actions require the
					type be specified, many do not (where the type can be reliably determined without ambiguity)
				</p>
				<p>
					Fields on an object become key/value pairs on the hash. Scalar values are provided as integers or strings
					interchangably
				</p>
				<p>
					Sequences become JSON arrays
				</p>

				<h5>Example of formatting</h5>

				<pre><code>
{
	"$<b></b>type": "ParentType",
	"Field": "Value",
	"FieldContainingObject": {
		"$<b></b>type": "ChildType"
		"Field": "Value"
	}
	"FieldContainingSequence": [
		{
			"$<b></b>type": "SequenceChildType",
			"Field": "Value"
		},	{
			"$<b></b>type": "SequenceChildType",
			"Field": "Value"
		}
	]
}
				</code></pre>

				<h4>Type mapping - text/xml</h4>

				<p>
					Objects become XML elements. The tag of the element is the type of the object.
				</p>
				<p>
					Fields on the object become child elements of the object element. The tag of the element is the field, the content is the value.
					Scalar values are provided without further type specification.
				</p>
				<p>
					Sequences become multiple elements listed one after another. There is no indicator of this other than repeated tags.
				</p>

				<h5>Example of formatting</h5>

				<pre><code>
&lt;?xml version=&quot;1.0&quot;?&gt;
&lt;ParentType&gt;
	&lt;Field&gt;Value&lt;/Field&gt;
	&lt;FieldContainingObject&gt;
		&lt;ChildType&gt;
			&lt;Field&gt;Value&lt;/Field&gt;
		&lt;/ChildType&gt;
	&lt;/FieldContainingObject&gt;
	&lt;FieldContainingSequence&gt;
		&lt;SequenceChildType&gt;
			&lt;Field&gt;Value&lt;/Field&gt;
		&lt;/SequenceChildType&gt;
		&lt;SequenceChildType&gt;
			&lt;Field&gt;Value&lt;/Field&gt;
		&lt;/SequenceChildType&gt;
	&lt;/FieldContainingSequence&gt;
&lt;/ParentType&gt;
				</code></pre>

				<h4>Type mapping - application/x-www-form-urlencoded</h4>

				<p>
					This type is only accepted for incoming data. It has no method of indicating type, or of describing sub-objects, and
					so can only be used when those features are not required
				</p>
				<p>
					The parent object is assumed to exist.
				</p>
				<p>
					Fields on the object become url key/value pairs
				</p>
				<p>
					Sequences can not be represented
				</p>

				<h5>Example of formatting</h5>

				<pre><code>
Field=Value&Field2=Value2
				</code></pre>

				<h1>Actions</h1>

				<% with Actions %>
				
					<% if $ReturnedTypes %>
						<ul class="semantic">
							<% loop $ReturnedTypes %>
								<li><% include RESTDocGenerator_Action %></li>
								<% loop $ReturnedTypes %>
									<li><% include RESTDocGenerator_Action %></li>
										<% loop $ReturnedTypes %>
											<li><% include RESTDocGenerator_Action %></li>
												<% loop $ReturnedTypes %>
													<li><% include RESTDocGenerator_Action %></li>

												<% end_loop %>
										<% end_loop %>
								<% end_loop %>
							<% end_loop %>
						</ul>
					<% else %>
						<div class="message info">
							<p>No classes found</p>
						</div>
					<% end_if %>
				<% end_with %>

				<h1>Types</h1>

				<ul class="semantic">
					<% loop Types.Types %>
						<li><% include RESTDocGenerator_Type %></li>
					<% end_loop %>
				</ul>
			</div>

			<div id="sidebar">
				<h3>Table of Contents</h3>
				<div id="contents">
					<ul>
						<li><a href='#actions-menu'>Actions</a></li>
						<li><a href='#types-menu'>Types</a></li>
					</ul>

					<div id='actions-menu'>
					<% loop $Actions %>
						<% if $ReturnedTypes %>
							<ul>
								<% loop $ReturnedTypes %>
								<li><a href="#action-$ID">/$Name</a>
										<% if $ReturnedTypes %>
											<ul>
											<% loop $ReturnedTypes %>
												<li><a href="#action-$ID">/$Name</a>
													<% if $ReturnedTypes %>
														<ul>
														<% loop $ReturnedTypes %>
															<li><a href="#action-$ID">/$Name</a>
																<% if $ReturnedTypes %>
																	<ul>
																	<% loop $ReturnedTypes %>
																		<li><a href="#action-$ID">/$Name</a></li>
																	<% end_loop %>
																	</ul>
																<% end_if %>
															</li>
														<% end_loop %>
														</ul>
													<% end_if %>
												</li>
												<% end_loop %>
											</ul>
										<% end_if %>
								</li>
								<% end_loop %>
							</ul>
						<% end_if %>
					<% end_loop %>
					</div>

					<div id='types-menu'>
					<ul>
						<% loop $Types.Types %>
							<li><a href="#type-$Class">$Class</a></li>
						<% end_loop %>
					</ul>
					</div>
				</div>
			</div>
		</div>
	</body>

	<script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js"></script>
	<script type="text/javascript" src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.8.18/jquery-ui.min.js"></script>

	<script type="text/javascript">
		jQuery(function($){
			$('#contents').tabs();

			$('code').each(function(){
				var pre = $(this).parent();

				pre.prev()
					.addClass('example-link')
					.on('click', function(){
						pre.show();
						return false;
					});

				$('body')
					.on('click', function(e){
						if (pre.is(':visible') && !$(e.target).parents().andSelf().is(pre)) pre.hide();
					});

				pre
					.addClass('popup-example')
					.hide();
			});

		});
	</script>

</html>
