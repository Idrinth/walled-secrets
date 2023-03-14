package de.idrinth.walled_secrets;

import javax.swing.JFrame;

public class ListFrame extends JFrame
{
    public ListFrame(String project, SwingFactory factory, Config config, Request request)
    {
        super("Secret List | " + project);
        setDefaultCloseOperation(JFrame.EXIT_ON_CLOSE);
        add(new ListPanel(factory, config, request));
        pack();
    }
}
