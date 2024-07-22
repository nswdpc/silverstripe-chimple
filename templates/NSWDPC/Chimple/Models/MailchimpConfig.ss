<div class="subscribe">

<% if $Heading %>
    <h3>{$Heading.XML}</h3>
<% end_if %>

<% if $BeforeFormContent %>
    <div class="content pre">
        {$BeforeFormContent}
    </div>
<% end_if %>

<% if $Image %>
    <div class="image">
        {$Image}
    </div>
<% end_if %>

    <div class="form">
        {$Form}
    </div>

<% if $AfterFormContent %>
    <div class="content post">
        {$AfterFormContent}
    </div>
<% end_if %>

</div>
