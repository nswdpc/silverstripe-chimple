<% if $Title && $ShowTitle %>
    <h2 class="content-element__title">{$Title.XML}</h2>
<% end_if %>
<div class="element element-content<% if $StyleVariant %> $StyleVariant<% end_if %>">
    {$SubscribeForm}
</div>
