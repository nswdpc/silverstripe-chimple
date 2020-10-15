
<h1>$Title</h1>

$Breadcrumbs

<section role="main">

    <% if $IsComplete == 'y' %>
        <p>Your subscription was successful</p>
    <% else_if $IsComplete == 'n' %>
        <p>Your subscription was not successful</p>
        <% include ChimpleSubscribeForm Code=$Code %>
    <% end_if %>

</div>
