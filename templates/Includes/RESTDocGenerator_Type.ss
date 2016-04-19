<div class="rest-class" id="type-$Class">
	<h2>$Class ($Type)</h2>

	$Description


	<table>
		<tr><th>Name</th><th>Type</th><th>Details</th></tr>
		<% loop $Fields %>
			<tr>
				<td>$Name</td>
				<td><% if Link %><a href="#type-$Link"><% end_if %>$Type<% if Link %></a><% end_if %></td>
				<td>$Description</td></tr>
		<% end_loop %>
	</table>

	<% if $SubClasses %>
		<% loop $SubClasses %>
			<h4>$Class</h4>

			$Description

			<table>
				<tr><th>Name</th><th>Type</th><th>Details</th></tr>
				<% loop $Fields %>
					<tr>
						<td>$Name</td>
						<td><% if Link %><a href="#type-$Link"><% end_if %>$Type<% if Link %></a><% end_if %></td>
						<td>$Description</td></tr>
				<% end_loop %>
			</table>
		<% end_loop %>
	<% end_if %>

</div>