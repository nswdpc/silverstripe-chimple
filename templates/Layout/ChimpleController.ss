
<h1>$Title</h1>

$Breadcrumbs

<section role="main">

    <% if $IsComplete == 'y' %>
        <p>You should receive a notification email shortly.</p>
    <% else_if $IsComplete == 'n' %>
        <p>Unfortunately your subscription request could not be completed, you could try again now or later.</p>
    <% end_if %>

    <% include ChimpleSubscribeForm Code=$Code %>

</div>
