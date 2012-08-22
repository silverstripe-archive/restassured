<div class="rest-class" id="action-$ID">
	<h2>$Name</h2>
	<h4>$URL</h4>

	<div class="rest-class-content">
		<% control Handler %>
			<% if Actions %>
				<ul class="semantic">
					<% control Actions %>
						</li>
							<h3>$Name</h3>

							$Description

							<% if Data %>
								<% control Data %>
									<h5>Request body</h5>
									<table>
										<tr><th>Request Type</th><th>Request Fields</th><th>Details</th></tr>
										<tr><td><a href='#type-$Type'>$Type</a></td><td>$Fields</td><td>$Body</td></tr>
									</table>
								<% end_control %>
							<% end_if %>

							<h5>Response on success</h5>
							<% control Response %>
								<table>
									<tr><th>Response Code</th><th>Details</th><th>Response Type</th><th>Response Fields</th></tr>
									<tr><td>$Code</td><td>$Body</td><td><a href='#type-$Type'>$Type</a></td><td>$Fields</td></tr>
								</table>
							<% end_control %>

							<% if ErrorResponses %>
								<h5>Responses on error</h5>
								<table>
									<tr><th>Response Code</th><th>Details</th><th>Response Type</th><th>Response Fields</th></tr>
									<% control ErrorResponses %>
										<tr><td>$Code</td><td>$Body</td><td><a href='#type-$Type'>$Type</a></td><td>$Fields</td></tr>
									<% end_control %>
								</table>
							<% end_if %>

						</li>
					<% end_control %>
				</ul>
			<% else %>
				<p>Not callable directly</p>
			<% end_if %>
		<% end_control %>
	</div>
</div>
