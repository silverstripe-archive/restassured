<div class="rest-class" id="type-$Class">
	<h2>$Class ($Type)</h2>

	$Description

	<table>
		<tr><th>Name</th><th>Type</th><th>Details</th></tr>
		<% control Fields %>
			<tr>
				<td>$Name</td>
				<td><% if Link %><a href="#type-$Link"><% end_if %>$Type<% if Link %></a><% end_if %></td>
				<td>$Description</td></tr>
		<% end_control %>
	</table>

	<% if SubClasses %>
		<% control SubClasses %>
			<h4>$Class</h4>

			$Description

			<table>
				<tr><th>Name</th><th>Type</th><th>Details</th></tr>
				<% control Fields %>
					<tr>
						<td>$Name</td>
						<td><% if Link %><a href="#type-$Link"><% end_if %>$Type<% if Link %></a><% end_if %></td>
						<td>$Description</td></tr>
				<% end_control %>
			</table>
		<% end_control %>
	<% end_if %>

</div>